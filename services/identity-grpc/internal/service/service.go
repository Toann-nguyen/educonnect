package service

import (
	"context"
	"errors"
	"log"
	"os"
	"strconv"
	"strings"
	"time"

	"golang.org/x/crypto/bcrypt"
	"google.golang.org/grpc"
	"google.golang.org/grpc/codes"
	"google.golang.org/grpc/credentials/insecure"
	"google.golang.org/grpc/metadata"
	"google.golang.org/grpc/status"
	"google.golang.org/protobuf/types/known/timestamppb"
	"gorm.io/gorm"
	"gorm.io/gorm/clause"

	"educonnect/identity-grpc/internal/events"
	"educonnect/identity-grpc/internal/model"
	"educonnect/identity-grpc/internal/phpclient"
	"educonnect/internal/pkg/proto/auth"
	"educonnect/internal/pkg/proto/common"
	"educonnect/internal/pkg/proto/school"
)

const userModelType = "App\\Domains\\Identity\\Models\\User"

type Servers struct {
	auth.UnimplementedAuthServiceServer
	auth.UnimplementedUserServiceServer
	db        *gorm.DB
	php       *phpclient.Client
	publisher events.Publisher
}

func New(db *gorm.DB, php *phpclient.Client, publisher events.Publisher) *Servers {
	return &Servers{db: db, php: php, publisher: publisher}
}

func tokenFromMetadata(ctx context.Context) (string, error) {
	md, ok := metadata.FromIncomingContext(ctx)
	if !ok {
		return "", status.Error(codes.Unauthenticated, "authorization metadata is required")
	}
	values := md.Get("authorization")
	if len(values) == 0 {
		return "", status.Error(codes.Unauthenticated, "bearer token is required")
	}
	token := strings.TrimSpace(values[0])
	token = strings.TrimPrefix(token, "Bearer ")
	if strings.TrimSpace(token) == "" {
		return "", status.Error(codes.Unauthenticated, "bearer token is required")
	}
	return token, nil
}

func parseID(value, field string) (uint, error) {
	id, err := strconv.ParseUint(strings.TrimSpace(value), 10, 32)
	if err != nil || id == 0 {
		return 0, status.Errorf(codes.InvalidArgument, "invalid %s", field)
	}
	return uint(id), nil
}

func timestamp(value time.Time) *timestamppb.Timestamp {
	if value.IsZero() {
		return nil
	}
	return timestamppb.New(value)
}

func roleToProto(name string) auth.UserRole {
	switch strings.ToLower(name) {
	case "student":
		return auth.UserRole_USER_ROLE_STUDENT
	case "teacher":
		return auth.UserRole_USER_ROLE_TEACHER
	case "admin":
		return auth.UserRole_USER_ROLE_ADMIN
	case "parent":
		return auth.UserRole_USER_ROLE_PARENT
	default:
		return auth.UserRole_USER_ROLE_UNSPECIFIED
	}
}

func roleToModel(role auth.UserRole) (string, error) {
	switch role {
	case auth.UserRole_USER_ROLE_STUDENT:
		return "student", nil
	case auth.UserRole_USER_ROLE_TEACHER:
		return "teacher", nil
	case auth.UserRole_USER_ROLE_ADMIN:
		return "admin", nil
	case auth.UserRole_USER_ROLE_PARENT:
		return "parent", nil
	default:
		return "", status.Error(codes.InvalidArgument, "valid role is required")
	}
}

func statusToProto(user model.User) auth.UserStatus {
	if user.DeletedAt.Valid {
		return auth.UserStatus_USER_STATUS_DELETED
	}
	if user.IsLocked {
		return auth.UserStatus_USER_STATUS_SUSPENDED
	}
	if !user.IsActive || user.Status == 0 {
		return auth.UserStatus_USER_STATUS_INACTIVE
	}
	return auth.UserStatus_USER_STATUS_ACTIVE
}

func (s *Servers) roleName(userID uint) string {
	var name string
	s.db.Table("roles").Select("roles.name").Joins("JOIN model_has_roles ON model_has_roles.role_id = roles.id").Where("model_has_roles.model_type = ? AND model_has_roles.model_id = ?", userModelType, userID).Order("roles.name").Limit(1).Scan(&name)
	return name
}

func (s *Servers) protoUser(user model.User) *auth.UserProfile {
	return &auth.UserProfile{
		Id:          strconv.FormatUint(uint64(user.ID), 10),
		Email:       user.Email,
		FullName:    user.Name,
		Phone:       user.Phone,
		AvatarUrl:   user.AvatarURL,
		Role:        roleToProto(s.roleName(user.ID)),
		Status:      statusToProto(user),
		LastLoginAt: timestamp(user.LastLoginAtValue()),
		CreatedAt:   timestamp(user.CreatedAt),
		UpdatedAt:   timestamp(user.UpdatedAt),
	}
}

func (s *Servers) loadUser(id uint) (model.User, error) {
	var user model.User
	if err := s.db.First(&user, id).Error; err != nil {
		if errors.Is(err, gorm.ErrRecordNotFound) {
			return model.User{}, status.Error(codes.NotFound, "user not found")
		}
		return model.User{}, status.Error(codes.Internal, "failed to load user")
	}
	return user, nil
}

func (s *Servers) authorize(ctx context.Context) (*phpclient.Caller, error) {
	token, err := tokenFromMetadata(ctx)
	if err != nil {
		return nil, err
	}
	return s.php.Me(ctx, token)
}

func requireAdmin(caller *phpclient.Caller) error {
	for _, role := range caller.Roles {
		if role == "admin" {
			return nil
		}
	}
	return status.Error(codes.PermissionDenied, "admin role is required")
}

func passwordPolicy(password string) error {
	if len(password) < 8 {
		return status.Error(codes.InvalidArgument, "password must be at least 8 characters")
	}
	var upper, lower, digit bool
	for _, r := range password {
		switch {
		case r >= 'A' && r <= 'Z':
			upper = true
		case r >= 'a' && r <= 'z':
			lower = true
		case r >= '0' && r <= '9':
			digit = true
		}
	}
	if !upper || !lower || !digit {
		return status.Error(codes.InvalidArgument, "password must contain upper-case, lower-case, and numeric characters")
	}
	return nil
}

func validEmail(email string) bool {
	email = strings.TrimSpace(email)
	at := strings.Index(email, "@")
	return at > 0 && strings.Contains(email[at:], ".")
}

func appendEvent(tx *gorm.DB, aggregateID uint, eventType string, payload map[string]any) error {
	var current uint32
	if err := tx.Table("event_store").Where("aggregate_type = ? AND aggregate_id = ?", "user", aggregateID).Select("COALESCE(MAX(version), 0)").Clauses(clause.Locking{Strength: "UPDATE"}).Scan(&current).Error; err != nil {
		return err
	}
	return tx.Table("event_store").Create(map[string]any{
		"aggregate_type": "user",
		"aggregate_id":   aggregateID,
		"version":        current + 1,
		"event_type":     eventType,
		"payload":        payload,
		"occurred_at":    time.Now().UTC(),
	}).Error
}

func userPayload(user model.User, roles []string) map[string]any {
	return map[string]any{
		"event": "user",
		"user": map[string]any{
			"id":        user.ID,
			"name":      user.Name,
			"email":     user.Email,
			"roles":     roles,
			"is_active": user.IsActive,
			"status":    user.Status,
		},
	}
}

func (s *Servers) publish(ctx context.Context, routingKey string, payload map[string]any) {
	if s.publisher == nil {
		return
	}
	if err := s.publisher.Publish(ctx, routingKey, payload); err != nil {
		log.Printf("user event publish failed: %s: %v", routingKey, err)
	}
}

func (s *Servers) ValidateToken(ctx context.Context, req *auth.ValidateTokenRequest) (*auth.ValidateTokenResponse, error) {
	if strings.TrimSpace(req.GetAccessToken()) == "" {
		return nil, status.Error(codes.InvalidArgument, "access token is required")
	}
	caller, err := s.php.Me(ctx, strings.TrimSpace(req.GetAccessToken()))
	if err != nil {
		if status.Code(err) == codes.Unauthenticated {
			return &auth.ValidateTokenResponse{Valid: false, Message: "invalid or expired token"}, nil
		}
		return nil, err
	}
	user, err := s.loadUser(caller.ID)
	if err != nil {
		return &auth.ValidateTokenResponse{Valid: false, Message: "user not found"}, nil
	}
	profile := s.protoUser(user)
	return &auth.ValidateTokenResponse{
		Valid:   true,
		User:    profile,
		Claims:  map[string]string{"user_id": profile.Id, "roles": strings.Join(caller.Roles, ",")},
		Message: "token is valid",
	}, nil
}

func (s *Servers) Login(ctx context.Context, req *auth.LoginRequest) (*auth.LoginResponse, error) {
	if !validEmail(req.GetEmail()) || strings.TrimSpace(req.GetPassword()) == "" {
		return nil, status.Error(codes.InvalidArgument, "email and password are required")
	}
	result, err := s.php.Login(ctx, strings.ToLower(strings.TrimSpace(req.GetEmail())), req.GetPassword())
	if err != nil {
		return nil, err
	}
	user, err := s.loadUser(uint(result.UserID))
	if err != nil {
		return nil, err
	}
	return &auth.LoginResponse{
		Tokens: &auth.TokenResponse{
			AccessToken:  result.AccessToken,
			RefreshToken: result.RefreshToken,
			ExpiresIn:    result.ExpiresIn,
			TokenType:    "Bearer",
		},
		User: s.protoUser(user),
	}, nil
}

func (s *Servers) RefreshToken(ctx context.Context, req *auth.RefreshTokenRequest) (*auth.TokenResponse, error) {
	result, err := s.php.Refresh(ctx, req.GetRefreshToken())
	if err != nil {
		return nil, err
	}
	tokenType := result.TokenType
	if tokenType == "" {
		tokenType = "Bearer"
	}
	return &auth.TokenResponse{
		AccessToken:  result.AccessToken,
		RefreshToken: result.RefreshToken,
		ExpiresIn:    result.ExpiresIn,
		TokenType:    tokenType,
	}, nil
}

func (s *Servers) Logout(ctx context.Context, req *common.UUID) (*common.Empty, error) {
	if err := s.php.Logout(ctx, req.GetValue()); err != nil {
		return nil, err
	}
	return &common.Empty{}, nil
}

func (s *Servers) GetUser(ctx context.Context, req *auth.GetUserRequest) (*auth.GetUserResponse, error) {
	if _, err := s.authorize(ctx); err != nil {
		return nil, err
	}
	id, err := parseID(req.GetId(), "user ID")
	if err != nil {
		return nil, err
	}
	user, err := s.loadUser(id)
	if err != nil {
		return nil, err
	}
	return &auth.GetUserResponse{User: s.protoUser(user)}, nil
}

func (s *Servers) GetUserByEmail(ctx context.Context, req *auth.GetUserByEmailRequest) (*auth.GetUserResponse, error) {
	if _, err := s.authorize(ctx); err != nil {
		return nil, err
	}
	email := strings.ToLower(strings.TrimSpace(req.GetEmail()))
	if !validEmail(email) {
		return nil, status.Error(codes.InvalidArgument, "valid email is required")
	}
	var user model.User
	if err := s.db.Where("email = ?", email).First(&user).Error; err != nil {
		if errors.Is(err, gorm.ErrRecordNotFound) {
			return nil, status.Error(codes.NotFound, "user not found")
		}
		return nil, status.Error(codes.Internal, "failed to load user")
	}
	return &auth.GetUserResponse{User: s.protoUser(user)}, nil
}

func (s *Servers) GetUsersByRole(ctx context.Context, req *auth.GetUsersByRoleRequest) (*auth.GetUsersResponse, error) {
	caller, err := s.authorize(ctx)
	if err != nil {
		return nil, err
	}
	if err := requireAdmin(caller); err != nil {
		return nil, err
	}
	roleName, err := roleToModel(req.GetRole())
	if err != nil {
		return nil, err
	}
	query := s.db.Table("users").Select("users.*").Joins("JOIN model_has_roles ON model_has_roles.model_id = users.id AND model_has_roles.model_type = ?", userModelType).Joins("JOIN roles ON roles.id = model_has_roles.role_id").Where("roles.name = ? AND roles.guard_name = ?", roleName, "api")
	switch req.GetStatus() {
	case auth.UserStatus_USER_STATUS_ACTIVE:
		query = query.Where("users.is_active = ? AND users.is_locked = ?", true, false)
	case auth.UserStatus_USER_STATUS_INACTIVE:
		query = query.Where("users.is_active = ?", false)
	case auth.UserStatus_USER_STATUS_SUSPENDED:
		query = query.Where("users.is_locked = ?", true)
	case auth.UserStatus_USER_STATUS_DELETED:
		query = query.Where("users.deleted_at IS NOT NULL")
	}
	var total int64
	if err := query.Count(&total).Error; err != nil {
		return nil, status.Error(codes.Internal, "failed to count users")
	}
	pageSize := int32(20)
	if req.GetPagination().GetPageSize() > 0 {
		pageSize = req.GetPagination().GetPageSize()
	}
	if pageSize > 100 {
		pageSize = 100
	}
	page := int32(1)
	if req.GetPagination().GetPage() > 0 {
		page = req.GetPagination().GetPage()
	}
	pages := int32(0)
	if total > 0 {
		pages = int32((total + int64(pageSize) - 1) / int64(pageSize))
	}
	var users []model.User
	if err := query.Order("users.id").Offset(int((page - 1) * pageSize)).Limit(int(pageSize)).Find(&users).Error; err != nil {
		return nil, status.Error(codes.Internal, "failed to list users")
	}
	profiles := make([]*auth.UserProfile, 0, len(users))
	for _, user := range users {
		profiles = append(profiles, s.protoUser(user))
	}
	return &auth.GetUsersResponse{Users: profiles, Pagination: &common.PaginationResponse{
		TotalItems:  int32(total),
		TotalPages:  pages,
		CurrentPage: page,
		PageSize:    pageSize,
		HasNext:     page < pages,
		HasPrev:     page > 1,
	}}, nil
}

func (s *Servers) CreateUser(ctx context.Context, req *auth.CreateUserRequest) (*auth.CreateUserResponse, error) {
	caller, err := s.authorize(ctx)
	if err != nil {
		return nil, err
	}
	if err := requireAdmin(caller); err != nil {
		return nil, err
	}
	email := strings.ToLower(strings.TrimSpace(req.GetEmail()))
	if !validEmail(email) {
		return nil, status.Error(codes.InvalidArgument, "valid email is required")
	}
	name := strings.TrimSpace(req.GetFullName())
	if len(name) < 2 {
		return nil, status.Error(codes.InvalidArgument, "full name is required")
	}
	if err := passwordPolicy(req.GetPassword()); err != nil {
		return nil, err
	}
	roleName := "student"
	if req.GetRole() != auth.UserRole_USER_ROLE_UNSPECIFIED {
		roleName, err = roleToModel(req.GetRole())
		if err != nil {
			return nil, err
		}
	}
	hash, err := bcrypt.GenerateFromPassword([]byte(req.GetPassword()), bcrypt.DefaultCost)
	if err != nil {
		return nil, status.Error(codes.Internal, "failed to hash password")
	}
	var user model.User
	err = s.db.Transaction(func(tx *gorm.DB) error {
		var existing int64
		if err := tx.Model(&model.User{}).Where("email = ?", email).Count(&existing).Error; err != nil {
			return err
		}
		if existing > 0 {
			return status.Error(codes.AlreadyExists, "email already exists")
		}
		var role model.Role
		if err := tx.Where("name = ? AND guard_name = ?", roleName, "api").First(&role).Error; err != nil {
			if errors.Is(err, gorm.ErrRecordNotFound) {
				return status.Errorf(codes.FailedPrecondition, "role not found: %s", roleName)
			}
			return status.Error(codes.Internal, "failed to load role")
		}
		user = model.User{
			Name:         name,
			Email:        email,
			Phone:        strings.TrimSpace(req.GetPhone()),
			Password:     string(hash),
			PasswordHash: string(hash),
			Status:       1,
			IsActive:     true,
			TokenVersion: 1,
		}
		if err := tx.Create(&user).Error; err != nil {
			return status.Error(codes.Internal, "failed to create user")
		}
		if err := tx.Create(&model.ModelHasRole{RoleID: role.ID, ModelType: userModelType, ModelID: uint64(user.ID)}).Error; err != nil {
			return status.Error(codes.Internal, "failed to assign role")
		}
		return appendEvent(tx, user.ID, "user.created", userPayload(user, []string{roleName}))
	})
	if err != nil {
		return nil, err
	}
	s.publish(ctx, "user.created", userPayload(user, []string{roleName}))
	created, err := s.loadUser(user.ID)
	if err != nil {
		return nil, err
	}
	return &auth.CreateUserResponse{User: s.protoUser(created), Success: true, Message: "User created"}, nil
}

func (s *Servers) UpdateUser(ctx context.Context, req *auth.UpdateUserRequest) (*auth.UpdateUserResponse, error) {
	caller, err := s.authorize(ctx)
	if err != nil {
		return nil, err
	}
	id, err := parseID(req.GetId(), "user ID")
	if err != nil {
		return nil, err
	}
	isAdmin := requireAdmin(caller) == nil
	if !isAdmin && caller.ID != id {
		return nil, status.Error(codes.PermissionDenied, "insufficient permission")
	}
	updates := make(map[string]any)
	if strings.TrimSpace(req.GetFullName()) != "" {
		updates["name"] = strings.TrimSpace(req.GetFullName())
	}
	if strings.TrimSpace(req.GetPhone()) != "" {
		updates["phone"] = strings.TrimSpace(req.GetPhone())
	}
	if strings.TrimSpace(req.GetAvatarUrl()) != "" {
		updates["avatar_url"] = strings.TrimSpace(req.GetAvatarUrl())
	}
	if len(updates) == 0 {
		return nil, status.Error(codes.InvalidArgument, "no updatable field provided")
	}
	if err := s.db.Transaction(func(tx *gorm.DB) error {
		if err := tx.Model(&model.User{}).Where("id = ?", id).Updates(updates).Error; err != nil {
			return status.Error(codes.Internal, "failed to update user")
		}
		return appendEvent(tx, id, "user.updated", map[string]any{"event": "user.updated", "user": map[string]any{"id": id}})
	}); err != nil {
		return nil, err
	}
	updated, err := s.loadUser(id)
	if err != nil {
		return nil, err
	}
	s.publish(ctx, "user.updated", userPayload(updated, nil))
	return &auth.UpdateUserResponse{User: s.protoUser(updated), Success: true, Message: "User updated"}, nil
}

func (s *Servers) ChangePassword(ctx context.Context, req *auth.ChangePasswordRequest) (*auth.ChangePasswordResponse, error) {
	caller, err := s.authorize(ctx)
	if err != nil {
		return nil, err
	}
	id, err := parseID(req.GetUserId(), "user ID")
	if err != nil {
		return nil, err
	}
	isAdmin := requireAdmin(caller) == nil
	if !isAdmin && caller.ID != id {
		return nil, status.Error(codes.PermissionDenied, "insufficient permission")
	}
	if err := passwordPolicy(req.GetNewPassword()); err != nil {
		return nil, err
	}
	user, err := s.loadUser(id)
	if err != nil {
		return nil, err
	}
	if !isAdmin || (isAdmin && strings.TrimSpace(req.GetOldPassword()) != "") {
		password := user.PasswordHash
		if password == "" {
			password = user.Password
		}
		if err := bcrypt.CompareHashAndPassword([]byte(password), []byte(req.GetOldPassword())); err != nil {
			return nil, status.Error(codes.PermissionDenied, "current password is invalid")
		}
	}
	hash, err := bcrypt.GenerateFromPassword([]byte(req.GetNewPassword()), bcrypt.DefaultCost)
	if err != nil {
		return nil, status.Error(codes.Internal, "failed to hash password")
	}
	if err := s.db.Transaction(func(tx *gorm.DB) error {
		if err := tx.Model(&model.User{}).Where("id = ?", id).Updates(map[string]any{
			"password":      string(hash),
			"password_hash": string(hash),
			"token_version": gorm.Expr("token_version + 1"),
		}).Error; err != nil {
			return status.Error(codes.Internal, "failed to change password")
		}
		return appendEvent(tx, id, "user.password_changed", map[string]any{"event": "user.password_changed", "user": map[string]any{"id": id}})
	}); err != nil {
		return nil, err
	}
	s.publish(ctx, "user.updated", map[string]any{"event": "user.updated", "user": map[string]any{"id": id}})
	return &auth.ChangePasswordResponse{Success: true, Message: "Password changed"}, nil
}

func schoolGRPCAddress() string {
	if address := os.Getenv("SCHOOL_GRPC_ADDR"); address != "" {
		return address
	}
	return "school-grpc:50053"
}

func (s *Servers) GetStudentProfile(ctx context.Context, req *auth.GetUserRequest) (*auth.GetStudentProfileResponse, error) {
	if _, err := s.authorize(ctx); err != nil {
		return nil, err
	}
	id, err := parseID(req.GetId(), "user ID")
	if err != nil {
		return nil, err
	}
	user, err := s.loadUser(id)
	if err != nil {
		return nil, err
	}
	conn, err := grpc.NewClient(schoolGRPCAddress(), grpc.WithTransportCredentials(insecure.NewCredentials()))
	if err != nil {
		return nil, status.Error(codes.Unavailable, "school service is unavailable")
	}
	defer conn.Close()
	student, err := school.NewStudentServiceClient(conn).GetStudentByUserId(ctx, &school.GetStudentByUserIdRequest{UserId: strconv.FormatUint(uint64(id), 10)})
	if err != nil {
		return nil, err
	}
	return &auth.GetStudentProfileResponse{Profile: &auth.StudentProfile{
		User:        s.protoUser(user),
		StudentCode: student.GetStudent().GetStudentCode(),
		ClassId:     student.GetStudent().GetClassId(),
	}}, nil
}

func (s *Servers) GetTeacherProfile(ctx context.Context, req *auth.GetUserRequest) (*auth.GetTeacherProfileResponse, error) {
	if _, err := s.authorize(ctx); err != nil {
		return nil, err
	}
	id, err := parseID(req.GetId(), "user ID")
	if err != nil {
		return nil, err
	}
	user, err := s.loadUser(id)
	if err != nil {
		return nil, err
	}
	conn, err := grpc.NewClient(schoolGRPCAddress(), grpc.WithTransportCredentials(insecure.NewCredentials()))
	if err != nil {
		return nil, status.Error(codes.Unavailable, "school service is unavailable")
	}
	defer conn.Close()
	teacher, err := school.NewTeacherServiceClient(conn).GetTeacherByUserId(ctx, &school.GetTeacherByUserIdRequest{UserId: strconv.FormatUint(uint64(id), 10)})
	if err != nil {
		return nil, err
	}
	return &auth.GetTeacherProfileResponse{Profile: &auth.TeacherProfile{
		User:        s.protoUser(user),
		TeacherCode: teacher.GetTeacher().GetTeacherCode(),
		Subjects:    teacher.GetTeacher().GetSubjects(),
		Department:  teacher.GetTeacher().GetDepartment(),
		HireDate:    teacher.GetTeacher().GetHireDate(),
	}}, nil
}
