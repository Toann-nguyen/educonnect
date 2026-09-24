package server

import (
	"context"
	"errors"
	"strconv"
	"strings"
	"time"

	"google.golang.org/grpc/codes"
	"google.golang.org/grpc/status"
	"google.golang.org/protobuf/types/known/timestamppb"
	"gorm.io/gorm"

	common "educonnect/internal/pkg/proto/common"
	"educonnect/internal/pkg/proto/school"
	"educonnect/school-grpc/internal/authclient"
	"educonnect/school-grpc/internal/model"
)

type Server struct {
	school.UnimplementedStudentServiceServer
	school.UnimplementedClassServiceServer
	school.UnimplementedGradeServiceServer
	school.UnimplementedScheduleServiceServer
	db *gorm.DB
}

func New(db *gorm.DB) *Server {
	return &Server{db: db}
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

func paginate(total int64, req *common.PaginationRequest) *common.PaginationResponse {
	pageSize := int32(20)
	if req != nil && req.PageSize > 0 {
		pageSize = req.PageSize
	}
	if pageSize > 100 {
		pageSize = 100
	}
	page := int32(1)
	if req != nil && req.Page > 0 {
		page = req.Page
	}
	pages := int32(0)
	if total > 0 {
		pages = int32((total + int64(pageSize) - 1) / int64(pageSize))
	}
	return &common.PaginationResponse{
		TotalItems:  int32(total),
		TotalPages:  pages,
		CurrentPage: page,
		PageSize:    pageSize,
		HasNext:     page < pages,
		HasPrev:     page > 1,
	}
}

func studentStatusToProto(value string) school.StudentStatus {
	switch strings.ToLower(strings.TrimSpace(value)) {
	case "studying", "active":
		return school.StudentStatus_STUDENT_STATUS_ACTIVE
	case "graduated":
		return school.StudentStatus_STUDENT_STATUS_GRADUATED
	case "suspended":
		return school.StudentStatus_STUDENT_STATUS_SUSPENDED
	case "inactive", "dropped":
		return school.StudentStatus_STUDENT_STATUS_INACTIVE
	default:
		return school.StudentStatus_STUDENT_STATUS_UNSPECIFIED
	}
}

func studentStatusToModel(value school.StudentStatus) (string, error) {
	switch value {
	case school.StudentStatus_STUDENT_STATUS_ACTIVE:
		return "studying", nil
	case school.StudentStatus_STUDENT_STATUS_INACTIVE:
		return "inactive", nil
	case school.StudentStatus_STUDENT_STATUS_GRADUATED:
		return "graduated", nil
	case school.StudentStatus_STUDENT_STATUS_SUSPENDED:
		return "suspended", nil
	default:
		return "", status.Error(codes.InvalidArgument, "valid student status is required")
	}
}

func gradeTypeToProto(value string) school.GradeType {
	switch strings.ToLower(strings.TrimSpace(value)) {
	case "quiz":
		return school.GradeType_GRADE_TYPE_QUIZ
	case "midterm":
		return school.GradeType_GRADE_TYPE_MIDTERM
	case "final":
		return school.GradeType_GRADE_TYPE_FINAL
	default:
		return school.GradeType_GRADE_TYPE_ASSIGNMENT
	}
}

func gradeTypeToModel(value school.GradeType) (string, error) {
	switch value {
	case school.GradeType_GRADE_TYPE_QUIZ:
		return "quiz", nil
	case school.GradeType_GRADE_TYPE_MIDTERM:
		return "midterm", nil
	case school.GradeType_GRADE_TYPE_FINAL:
		return "final", nil
	case school.GradeType_GRADE_TYPE_ASSIGNMENT:
		return "assignment", nil
	default:
		return "", status.Error(codes.InvalidArgument, "valid grade type is required")
	}
}

func classIDString(id *uint) string {
	if id == nil || *id == 0 {
		return ""
	}
	return strconv.FormatUint(uint64(*id), 10)
}

func protoStudent(value model.Student) *school.Student {
	return &school.Student{
		Id:          strconv.FormatUint(uint64(value.ID), 10),
		UserId:      strconv.FormatUint(uint64(value.UserID), 10),
		ClassId:     classIDString(value.ClassID),
		StudentCode: value.StudentCode,
		Status:      studentStatusToProto(value.Status),
		User:        &common.UserRef{Id: strconv.FormatUint(uint64(value.UserID), 10)},
		CreatedAt:   timestamp(value.CreatedAt),
		UpdatedAt:   timestamp(value.UpdatedAt),
	}
}

func (s *Server) isParentOf(studentID, callerID uint) bool {
	var count int64
	s.db.Model(&model.StudentGuardian{}).Where("student_id = ? AND guardian_user_id = ?", studentID, callerID).Count(&count)
	return count > 0
}

func (s *Server) loadStudent(id uint) (model.Student, error) {
	var student model.Student
	if err := s.db.First(&student, id).Error; err != nil {
		if errors.Is(err, gorm.ErrRecordNotFound) {
			return model.Student{}, status.Error(codes.NotFound, "student not found")
		}
		return model.Student{}, status.Error(codes.Internal, "failed to load student")
	}
	return student, nil
}

func (s *Server) canReadStudent(caller *authclient.Caller, student model.Student) bool {
	if caller.IsStaff() {
		return true
	}
	if student.UserID == caller.ID {
		return true
	}
	return s.isParentOf(student.ID, caller.ID)
}

func (s *Server) GetStudent(ctx context.Context, req *school.GetStudentRequest) (*school.GetStudentResponse, error) {
	caller, err := authclient.Authenticate(ctx)
	if err != nil {
		return nil, err
	}
	id, err := parseID(req.GetId(), "student ID")
	if err != nil {
		return nil, err
	}
	student, err := s.loadStudent(id)
	if err != nil {
		return nil, err
	}
	if !s.canReadStudent(caller, student) {
		return nil, status.Error(codes.PermissionDenied, "insufficient permission")
	}
	return &school.GetStudentResponse{Student: protoStudent(student)}, nil
}

func (s *Server) GetStudentByUserId(ctx context.Context, req *school.GetStudentByUserIdRequest) (*school.GetStudentResponse, error) {
	caller, err := authclient.Authenticate(ctx)
	if err != nil {
		return nil, err
	}
	userID, err := parseID(req.GetUserId(), "user ID")
	if err != nil {
		return nil, err
	}
	var student model.Student
	if err := s.db.Where("user_id = ?", userID).First(&student).Error; err != nil {
		if errors.Is(err, gorm.ErrRecordNotFound) {
			return nil, status.Error(codes.NotFound, "student not found")
		}
		return nil, status.Error(codes.Internal, "failed to load student")
	}
	if !s.canReadStudent(caller, student) {
		return nil, status.Error(codes.PermissionDenied, "insufficient permission")
	}
	return &school.GetStudentResponse{Student: protoStudent(student)}, nil
}

func (s *Server) ListStudents(ctx context.Context, req *school.ListStudentsRequest) (*school.ListStudentsResponse, error) {
	caller, err := authclient.Authenticate(ctx)
	if err != nil {
		return nil, err
	}
	if !caller.IsStaff() {
		return nil, status.Error(codes.PermissionDenied, "insufficient permission")
	}
	query := s.db.Model(&model.Student{})
	if strings.TrimSpace(req.GetClassId()) != "" {
		classID, err := parseID(req.GetClassId(), "class ID")
		if err != nil {
			return nil, err
		}
		query = query.Where("class_id = ?", classID)
	}
	if req.GetStatus() != school.StudentStatus_STUDENT_STATUS_UNSPECIFIED {
		modelStatus, err := studentStatusToModel(req.GetStatus())
		if err != nil {
			return nil, err
		}
		query = query.Where("status = ?", modelStatus)
	}
	var total int64
	if err := query.Count(&total).Error; err != nil {
		return nil, status.Error(codes.Internal, "failed to count students")
	}
	pagination := paginate(total, req.GetPagination())
	offset := int((pagination.CurrentPage - 1) * pagination.PageSize)
	var students []model.Student
	if err := query.Order("id").Offset(offset).Limit(int(pagination.PageSize)).Find(&students).Error; err != nil {
		return nil, status.Error(codes.Internal, "failed to list students")
	}
	protoStudents := make([]*school.Student, 0, len(students))
	for _, student := range students {
		protoStudents = append(protoStudents, protoStudent(student))
	}
	return &school.ListStudentsResponse{Students: protoStudents, Pagination: pagination}, nil
}

func (s *Server) CreateStudent(ctx context.Context, req *school.CreateStudentRequest) (*school.CreateStudentResponse, error) {
	caller, err := authclient.Authenticate(ctx)
	if err != nil {
		return nil, err
	}
	if !caller.IsWriter() {
		return nil, status.Error(codes.PermissionDenied, "insufficient permission")
	}
	userID, err := parseID(req.GetUserId(), "user ID")
	if err != nil {
		return nil, err
	}
	student := model.Student{UserID: userID, StudentCode: strings.TrimSpace(req.GetStudentCode()), Status: "studying"}
	if student.StudentCode == "" {
		return nil, status.Error(codes.InvalidArgument, "student code is required")
	}
	if strings.TrimSpace(req.GetClassId()) != "" {
		classID, err := parseID(req.GetClassId(), "class ID")
		if err != nil {
			return nil, err
		}
		var class model.SchoolClass
		if err := s.db.First(&class, classID).Error; err != nil {
			if errors.Is(err, gorm.ErrRecordNotFound) {
				return nil, status.Error(codes.NotFound, "class not found")
			}
			return nil, status.Error(codes.Internal, "failed to validate class")
		}
		student.ClassID = &classID
	}
	if err := s.db.Create(&student).Error; err != nil {
		return nil, status.Error(codes.Internal, "failed to create student")
	}
	return &school.CreateStudentResponse{Student: protoStudent(student), Success: true, Message: "Student created"}, nil
}

func (s *Server) UpdateStudent(ctx context.Context, req *school.UpdateStudentRequest) (*school.UpdateStudentResponse, error) {
	caller, err := authclient.Authenticate(ctx)
	if err != nil {
		return nil, err
	}
	if !caller.IsWriter() {
		return nil, status.Error(codes.PermissionDenied, "insufficient permission")
	}
	id, err := parseID(req.GetId(), "student ID")
	if err != nil {
		return nil, err
	}
	student, err := s.loadStudent(id)
	if err != nil {
		return nil, err
	}
	updates := make(map[string]any)
	if strings.TrimSpace(req.GetClassId()) != "" {
		classID, err := parseID(req.GetClassId(), "class ID")
		if err != nil {
			return nil, err
		}
		var class model.SchoolClass
		if err := s.db.First(&class, classID).Error; err != nil {
			if errors.Is(err, gorm.ErrRecordNotFound) {
				return nil, status.Error(codes.NotFound, "class not found")
			}
			return nil, status.Error(codes.Internal, "failed to validate class")
		}
		updates["class_id"] = classID
	}
	if req.GetStatus() != school.StudentStatus_STUDENT_STATUS_UNSPECIFIED {
		modelStatus, err := studentStatusToModel(req.GetStatus())
		if err != nil {
			return nil, err
		}
		updates["status"] = modelStatus
	}
	if len(updates) == 0 {
		return nil, status.Error(codes.InvalidArgument, "no updatable field provided")
	}
	if err := s.db.Model(&model.Student{}).Where("id = ?", student.ID).Updates(updates).Error; err != nil {
		return nil, status.Error(codes.Internal, "failed to update student")
	}
	updated, err := s.loadStudent(student.ID)
	if err != nil {
		return nil, err
	}
	return &school.UpdateStudentResponse{Student: protoStudent(updated), Success: true, Message: "Student updated"}, nil
}

func (s *Server) GetClass(ctx context.Context, req *school.GetClassRequest) (*school.GetClassResponse, error) {
	caller, err := authclient.Authenticate(ctx)
	if err != nil {
		return nil, err
	}
	if !caller.IsWriter() {
		return nil, status.Error(codes.PermissionDenied, "insufficient permission")
	}
	id, err := parseID(req.GetId(), "class ID")
	if err != nil {
		return nil, err
	}
	var class model.SchoolClass
	if err := s.db.First(&class, id).Error; err != nil {
		if errors.Is(err, gorm.ErrRecordNotFound) {
			return nil, status.Error(codes.NotFound, "class not found")
		}
		return nil, status.Error(codes.Internal, "failed to load class")
	}
	return &school.GetClassResponse{Class: &school.SchoolClass{
		Id:                strconv.FormatUint(uint64(class.ID), 10),
		Name:              class.Name,
		AcademicYearId:    strconv.FormatUint(uint64(class.AcademicYearID), 10),
		HomeroomTeacherId: strconv.FormatUint(uint64(class.HomeroomTeacherID), 10),
		CreatedAt:         timestamp(class.CreatedAt),
		UpdatedAt:         timestamp(class.UpdatedAt),
	}}, nil
}

func (s *Server) ListClasses(ctx context.Context, req *school.ListClassesRequest) (*school.ListClassesResponse, error) {
	caller, err := authclient.Authenticate(ctx)
	if err != nil {
		return nil, err
	}
	if !caller.IsWriter() {
		return nil, status.Error(codes.PermissionDenied, "insufficient permission")
	}
	query := s.db.Model(&model.SchoolClass{})
	if strings.TrimSpace(req.GetAcademicYearId()) != "" {
		yearID, err := parseID(req.GetAcademicYearId(), "academic year ID")
		if err != nil {
			return nil, err
		}
		query = query.Where("academic_year_id = ?", yearID)
	}
	var total int64
	if err := query.Count(&total).Error; err != nil {
		return nil, status.Error(codes.Internal, "failed to count classes")
	}
	pagination := paginate(total, req.GetPagination())
	offset := int((pagination.CurrentPage - 1) * pagination.PageSize)
	var classes []model.SchoolClass
	if err := query.Order("id").Offset(offset).Limit(int(pagination.PageSize)).Find(&classes).Error; err != nil {
		return nil, status.Error(codes.Internal, "failed to list classes")
	}
	protoClasses := make([]*school.SchoolClass, 0, len(classes))
	for _, class := range classes {
		protoClasses = append(protoClasses, &school.SchoolClass{
			Id:                strconv.FormatUint(uint64(class.ID), 10),
			Name:              class.Name,
			AcademicYearId:    strconv.FormatUint(uint64(class.AcademicYearID), 10),
			HomeroomTeacherId: strconv.FormatUint(uint64(class.HomeroomTeacherID), 10),
			CreatedAt:         timestamp(class.CreatedAt),
			UpdatedAt:         timestamp(class.UpdatedAt),
		})
	}
	return &school.ListClassesResponse{Classes: protoClasses, Pagination: pagination}, nil
}

func (s *Server) GetGrades(ctx context.Context, req *school.GetGradesRequest) (*school.GetGradesResponse, error) {
	caller, err := authclient.Authenticate(ctx)
	if err != nil {
		return nil, err
	}
	query := s.db.Model(&model.Grade{})
	if strings.TrimSpace(req.GetStudentId()) != "" {
		studentID, err := parseID(req.GetStudentId(), "student ID")
		if err != nil {
			return nil, err
		}
		if !caller.IsStaff() {
			student, err := s.loadStudent(studentID)
			if err != nil {
				return nil, err
			}
			if !s.canReadStudent(caller, student) {
				return nil, status.Error(codes.PermissionDenied, "insufficient permission")
			}
		}
		query = query.Where("student_id = ?", studentID)
	} else if !caller.IsStaff() {
		return nil, status.Error(codes.PermissionDenied, "student filter is required")
	}
	if strings.TrimSpace(req.GetSubjectId()) != "" {
		subjectID, err := parseID(req.GetSubjectId(), "subject ID")
		if err != nil {
			return nil, err
		}
		query = query.Where("subject_id = ?", subjectID)
	}
	if req.GetSemester() != 0 {
		if req.GetSemester() != 1 && req.GetSemester() != 2 {
			return nil, status.Error(codes.InvalidArgument, "semester must be 1 or 2")
		}
		query = query.Where("semester = ?", req.GetSemester())
	}
	var total int64
	if err := query.Count(&total).Error; err != nil {
		return nil, status.Error(codes.Internal, "failed to count grades")
	}
	pagination := paginate(total, req.GetPagination())
	offset := int((pagination.CurrentPage - 1) * pagination.PageSize)
	var grades []model.Grade
	if err := query.Order("id").Offset(offset).Limit(int(pagination.PageSize)).Find(&grades).Error; err != nil {
		return nil, status.Error(codes.Internal, "failed to list grades")
	}
	protoGrades := make([]*school.Grade, 0, len(grades))
	for _, grade := range grades {
		protoGrades = append(protoGrades, &school.Grade{
			Id:        strconv.FormatUint(uint64(grade.ID), 10),
			StudentId: strconv.FormatUint(uint64(grade.StudentID), 10),
			SubjectId: strconv.FormatUint(uint64(grade.SubjectID), 10),
			TeacherId: strconv.FormatUint(uint64(grade.TeacherID), 10),
			Score:     grade.Score,
			Type:      gradeTypeToProto(grade.Type),
			Semester:  int32(grade.Semester),
			CreatedAt: timestamp(grade.CreatedAt),
			UpdatedAt: timestamp(grade.UpdatedAt),
		})
	}
	return &school.GetGradesResponse{Grades: protoGrades, Pagination: pagination}, nil
}

func (s *Server) CreateGrade(ctx context.Context, req *school.CreateGradeRequest) (*school.CreateGradeResponse, error) {
	caller, err := authclient.Authenticate(ctx)
	if err != nil {
		return nil, err
	}
	if !caller.HasRole("teacher", "admin") {
		return nil, status.Error(codes.PermissionDenied, "insufficient permission")
	}
	studentID, err := parseID(req.GetStudentId(), "student ID")
	if err != nil {
		return nil, err
	}
	subjectID, err := parseID(req.GetSubjectId(), "subject ID")
	if err != nil {
		return nil, err
	}
	if req.GetScore() < 0 || req.GetScore() > 10 {
		return nil, status.Error(codes.InvalidArgument, "score must be between 0 and 10")
	}
	if req.GetSemester() != 1 && req.GetSemester() != 2 {
		return nil, status.Error(codes.InvalidArgument, "semester must be 1 or 2")
	}
	gradeType, err := gradeTypeToModel(req.GetType())
	if err != nil {
		return nil, err
	}
	var student model.Student
	if err := s.db.First(&student, studentID).Error; err != nil {
		if errors.Is(err, gorm.ErrRecordNotFound) {
			return nil, status.Error(codes.NotFound, "student not found")
		}
		return nil, status.Error(codes.Internal, "failed to validate student")
	}
	var subject model.Subject
	if err := s.db.First(&subject, subjectID).Error; err != nil {
		if errors.Is(err, gorm.ErrRecordNotFound) {
			return nil, status.Error(codes.NotFound, "subject not found")
		}
		return nil, status.Error(codes.Internal, "failed to validate subject")
	}
	grade := model.Grade{
		StudentID: studentID,
		SubjectID: subjectID,
		TeacherID: caller.ID,
		Score:     req.GetScore(),
		Type:      gradeType,
		Semester:  int(req.GetSemester()),
	}
	if err := s.db.Create(&grade).Error; err != nil {
		return nil, status.Error(codes.Internal, "failed to create grade")
	}
	return &school.CreateGradeResponse{Grade: &school.Grade{
		Id:        strconv.FormatUint(uint64(grade.ID), 10),
		StudentId: strconv.FormatUint(uint64(grade.StudentID), 10),
		SubjectId: strconv.FormatUint(uint64(grade.SubjectID), 10),
		TeacherId: strconv.FormatUint(uint64(grade.TeacherID), 10),
		Score:     grade.Score,
		Type:      gradeTypeToProto(grade.Type),
		Semester:  int32(grade.Semester),
		CreatedAt: timestamp(grade.CreatedAt),
		UpdatedAt: timestamp(grade.UpdatedAt),
	}, Success: true, Message: "Grade created"}, nil
}

func (s *Server) GetSchedule(ctx context.Context, req *school.GetScheduleRequest) (*school.GetScheduleResponse, error) {
	caller, err := authclient.Authenticate(ctx)
	if err != nil {
		return nil, err
	}
	if !caller.IsStaff() {
		return nil, status.Error(codes.PermissionDenied, "insufficient permission")
	}
	id, err := parseID(req.GetId(), "schedule ID")
	if err != nil {
		return nil, err
	}
	var value model.Schedule
	if err := s.db.First(&value, id).Error; err != nil {
		if errors.Is(err, gorm.ErrRecordNotFound) {
			return nil, status.Error(codes.NotFound, "schedule not found")
		}
		return nil, status.Error(codes.Internal, "failed to load schedule")
	}
	room := ""
	if value.Room != nil {
		room = *value.Room
	}
	return &school.GetScheduleResponse{Schedule: &school.Schedule{
		Id:        strconv.FormatUint(uint64(value.ID), 10),
		ClassId:   strconv.FormatUint(uint64(value.ClassID), 10),
		SubjectId: strconv.FormatUint(uint64(value.SubjectID), 10),
		TeacherId: strconv.FormatUint(uint64(value.TeacherID), 10),
		DayOfWeek: int32(value.DayOfWeek),
		Period:    int32(value.Period),
		Room:      room,
		CreatedAt: timestamp(value.CreatedAt),
	}}, nil
}

func (s *Server) ListSchedules(ctx context.Context, req *school.ListSchedulesRequest) (*school.ListSchedulesResponse, error) {
	caller, err := authclient.Authenticate(ctx)
	if err != nil {
		return nil, err
	}
	if !caller.IsStaff() {
		return nil, status.Error(codes.PermissionDenied, "insufficient permission")
	}
	query := s.db.Model(&model.Schedule{})
	if strings.TrimSpace(req.GetClassId()) != "" {
		classID, err := parseID(req.GetClassId(), "class ID")
		if err != nil {
			return nil, err
		}
		query = query.Where("class_id = ?", classID)
	}
	if strings.TrimSpace(req.GetTeacherId()) != "" {
		teacherID, err := parseID(req.GetTeacherId(), "teacher ID")
		if err != nil {
			return nil, err
		}
		query = query.Where("teacher_id = ?", teacherID)
	}
	var total int64
	if err := query.Count(&total).Error; err != nil {
		return nil, status.Error(codes.Internal, "failed to count schedules")
	}
	pagination := paginate(total, req.GetPagination())
	offset := int((pagination.CurrentPage - 1) * pagination.PageSize)
	var values []model.Schedule
	if err := query.Order("day_of_week").Order("period").Order("id").Offset(offset).Limit(int(pagination.PageSize)).Find(&values).Error; err != nil {
		return nil, status.Error(codes.Internal, "failed to list schedules")
	}
	protoSchedules := make([]*school.Schedule, 0, len(values))
	for _, value := range values {
		room := ""
		if value.Room != nil {
			room = *value.Room
		}
		protoSchedules = append(protoSchedules, &school.Schedule{
			Id:        strconv.FormatUint(uint64(value.ID), 10),
			ClassId:   strconv.FormatUint(uint64(value.ClassID), 10),
			SubjectId: strconv.FormatUint(uint64(value.SubjectID), 10),
			TeacherId: strconv.FormatUint(uint64(value.TeacherID), 10),
			DayOfWeek: int32(value.DayOfWeek),
			Period:    int32(value.Period),
			Room:      room,
			CreatedAt: timestamp(value.CreatedAt),
		})
	}
	return &school.ListSchedulesResponse{Schedules: protoSchedules, Pagination: pagination}, nil
}
