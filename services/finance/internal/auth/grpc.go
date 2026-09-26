package auth

import (
	"context"
	"encoding/json"
	"fmt"
	"strconv"
	"strings"

	"github.com/golang-jwt/jwt/v5"
	"google.golang.org/grpc/codes"
	"google.golang.org/grpc/metadata"
	"google.golang.org/grpc/status"
	"gorm.io/gorm"

	"educonnect/finance/internal/model"
)

type GRPCClaims struct {
	UserID uint
	Roles  []string
	Claims jwt.MapClaims
}

func tokenFromMetadata(ctx context.Context) (string, error) {
	md, ok := metadata.FromIncomingContext(ctx)
	if !ok {
		return "", status.Error(codes.Unauthenticated, "authorization metadata is required")
	}
	values := md.Get("authorization")
	if len(values) == 0 {
		return "", status.Error(codes.Unauthenticated, "authorization metadata is required")
	}
	token := strings.TrimSpace(values[0])
	token = strings.TrimPrefix(token, "Bearer ")
	token = strings.TrimSpace(token)
	if token == "" {
		return "", status.Error(codes.Unauthenticated, "bearer token is required")
	}
	return token, nil
}

func userIDFromClaims(claims jwt.MapClaims) (uint, error) {
	switch sub := claims["sub"].(type) {
	case string:
		id, err := strconv.ParseUint(strings.TrimSpace(sub), 10, 32)
		if err != nil || id == 0 {
			return 0, status.Error(codes.Unauthenticated, "invalid token subject")
		}
		return uint(id), nil
	case float64:
		if sub <= 0 {
			return 0, status.Error(codes.Unauthenticated, "invalid token subject")
		}
		return uint(sub), nil
	default:
		return 0, status.Error(codes.Unauthenticated, "invalid token subject")
	}
}

func checkRevocation(claims jwt.MapClaims, userID uint) error {
	userIDText := strconv.FormatUint(uint64(userID), 10)
	if jti, _ := claims["jti"].(string); IsJTIBlacklisted(jti) {
		return status.Error(codes.Unauthenticated, "token has been revoked")
	}
	if sid, _ := claims["sid"].(string); IsSidRevoked(sid) {
		return status.Error(codes.Unauthenticated, "session has been revoked")
	}
	if tvRaw, ok := claims["tv"]; ok {
		var tv float64
		switch value := tvRaw.(type) {
		case float64:
			tv = value
		case int:
			tv = float64(value)
		case int64:
			tv = float64(value)
		}
		if IsTVStale(userIDText, tv) {
			return status.Error(codes.Unauthenticated, "token version is stale")
		}
	} else if verRaw, ok := claims["ver"]; ok {
		var tv float64
		switch value := verRaw.(type) {
		case float64:
			tv = value
		case int:
			tv = float64(value)
		case int64:
			tv = float64(value)
		}
		if IsTVStale(userIDText, tv) {
			return status.Error(codes.Unauthenticated, "token version is stale")
		}
	}
	if IsUserRevoked(userIDText) || IsUserRevoked(fmt.Sprint(claims["sub"])) {
		return status.Error(codes.PermissionDenied, "account has been revoked")
	}
	return nil
}

func AuthorizeGRPC(ctx context.Context, db *gorm.DB, permissions ...string) (*GRPCClaims, error) {
	token, err := tokenFromMetadata(ctx)
	if err != nil {
		return nil, err
	}
	claims, err := verifyToken(token)
	if err != nil {
		return nil, status.Error(codes.Unauthenticated, "invalid or expired token")
	}
	userID, err := userIDFromClaims(claims)
	if err != nil {
		return nil, err
	}
	if err := checkRevocation(claims, userID); err != nil {
		return nil, err
	}
	roles := rolesFromClaims(claims)
	if len(roles) == 0 && db != nil {
		// Token legacy không mang roles — lấy từ users_read_model đã sync qua user_events.
		roles = rolesFromReadModel(db, userID)
		if len(roles) > 0 {
			raw := make([]any, len(roles))
			for i, role := range roles {
				raw[i] = role
			}
			claims["roles"] = raw
		}
	}
	if len(permissions) > 0 && !HasPermission(claims, permissions...) {
		return nil, status.Error(codes.PermissionDenied, "insufficient permission")
	}
	return &GRPCClaims{UserID: userID, Roles: roles, Claims: claims}, nil
}

func rolesFromReadModel(db *gorm.DB, userID uint) []string {
	var user model.UserReadModel
	if err := db.Where("id = ?", userID).First(&user).Error; err != nil {
		return nil
	}
	var roles []string
	if err := json.Unmarshal([]byte(user.Roles), &roles); err != nil {
		return nil
	}
	return roles
}

func isAllAccessRoles(roles []string) bool {
	for _, role := range roles {
		switch strings.ToLower(role) {
		case RolePrincipal, RoleAdmin, RoleAccountant:
			return true
		}
	}
	return false
}

func isStudentOnlyRoles(roles []string) bool {
	student := false
	for _, role := range roles {
		switch strings.ToLower(role) {
		case RoleStudent, RoleParent:
			student = true
		case RolePrincipal, RoleAdmin, RoleAccountant, RoleHomeroom, RoleTeacher:
			return false
		}
	}
	return student
}

func isTeacherScopedRoles(roles []string) bool {
	teacher := false
	for _, role := range roles {
		switch strings.ToLower(role) {
		case RoleHomeroom, RoleTeacher:
			teacher = true
		case RolePrincipal, RoleAdmin, RoleAccountant:
			return false
		}
	}
	return teacher
}

func FilterInvoicesGRPC(db *gorm.DB, claims *GRPCClaims) *gorm.DB {
	if isAllAccessRoles(claims.Roles) {
		return db
	}
	if isStudentOnlyRoles(claims.Roles) {
		return db.Where("student_id = ?", claims.UserID)
	}
	if isTeacherScopedRoles(claims.Roles) {
		var classIDs []uint
		db.Model(&TeacherClassAssignment{}).Where("teacher_id = ?", claims.UserID).Pluck("class_id", &classIDs)
		if len(classIDs) > 0 {
			var studentIDs []uint
			db.Model(&StudentClass{}).Where("class_id IN ?", classIDs).Pluck("student_id", &studentIDs)
			if len(studentIDs) == 0 {
				return db.Where("1 = 0")
			}
			return db.Where("student_id IN ?", studentIDs)
		}
	}
	return db
}

func CanAccessInvoiceGRPC(db *gorm.DB, claims *GRPCClaims, invoice *model.Invoice) bool {
	if isAllAccessRoles(claims.Roles) {
		return true
	}
	if isStudentOnlyRoles(claims.Roles) {
		return invoice.StudentID == claims.UserID
	}
	if isTeacherScopedRoles(claims.Roles) {
		var classIDs []uint
		db.Model(&TeacherClassAssignment{}).Where("teacher_id = ?", claims.UserID).Pluck("class_id", &classIDs)
		if len(classIDs) == 0 {
			return false
		}
		var count int64
		db.Model(&StudentClass{}).Where("student_id = ? AND class_id IN ?", invoice.StudentID, classIDs).Count(&count)
		return count > 0
	}
	return false
}
