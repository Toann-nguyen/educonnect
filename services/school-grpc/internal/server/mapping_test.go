package server

import (
	"testing"

	"educonnect/internal/pkg/proto/school"
	"educonnect/school-grpc/internal/model"
)

func TestStudentStatusMapping(t *testing.T) {
	if got := studentStatusToProto("studying"); got != school.StudentStatus_STUDENT_STATUS_ACTIVE {
		t.Fatalf("unexpected status: %v", got)
	}
	if got, err := studentStatusToModel(school.StudentStatus_STUDENT_STATUS_GRADUATED); err != nil || got != "graduated" {
		t.Fatalf("unexpected model status: %v %v", got, err)
	}
	if _, err := studentStatusToModel(school.StudentStatus_STUDENT_STATUS_UNSPECIFIED); err == nil {
		t.Fatal("expected unspecified status to fail")
	}
}

func TestGradeTypeMapping(t *testing.T) {
	if got := gradeTypeToProto("15min"); got != school.GradeType_GRADE_TYPE_ASSIGNMENT {
		t.Fatalf("unexpected grade type: %v", got)
	}
	if got, err := gradeTypeToModel(school.GradeType_GRADE_TYPE_FINAL); err != nil || got != "final" {
		t.Fatalf("unexpected model grade type: %v %v", got, err)
	}
}

func TestProtoStudent(t *testing.T) {
	classID := uint(3)
	student := model.Student{ID: 7, UserID: 9, ClassID: &classID, StudentCode: "STU-1", Status: "studying"}
	converted := protoStudent(student)
	if converted.GetId() != "7" || converted.GetClassId() != "3" || converted.GetUser().GetId() != "9" {
		t.Fatalf("unexpected student: %+v", converted)
	}
	withoutClass := model.Student{ID: 8, UserID: 10, StudentCode: "STU-2", Status: "graduated"}
	if got := protoStudent(withoutClass); got.GetClassId() != "" || got.GetStatus() != school.StudentStatus_STUDENT_STATUS_GRADUATED {
		t.Fatalf("unexpected student without class: %+v", got)
	}
}
