package model

import (
	"time"

	"gorm.io/gorm"
)

type Student struct {
	ID          uint   `gorm:"primaryKey"`
	UserID      uint   `gorm:"index"`
	ClassID     *uint  `gorm:"index"`
	StudentCode string `gorm:"uniqueIndex"`
	Status      string `gorm:"default:studying"`
	CreatedAt   time.Time
	UpdatedAt   time.Time
	DeletedAt   gorm.DeletedAt `gorm:"index"`
}

func (Student) TableName() string { return "students" }

type SchoolClass struct {
	ID                uint `gorm:"primaryKey"`
	Name              string
	AcademicYearID    uint
	HomeroomTeacherID uint
	CreatedAt         time.Time
	UpdatedAt         time.Time
}

func (SchoolClass) TableName() string { return "classes" }

type Subject struct {
	ID          uint `gorm:"primaryKey"`
	Name        string
	SubjectCode string `gorm:"uniqueIndex"`
	CreatedAt   time.Time
	UpdatedAt   time.Time
}

func (Subject) TableName() string { return "subjects" }

type Grade struct {
	ID        uint `gorm:"primaryKey"`
	StudentID uint `gorm:"index"`
	SubjectID uint `gorm:"index"`
	TeacherID uint `gorm:"index"`
	Score     float64
	Type      string
	Semester  int
	CreatedAt time.Time
	UpdatedAt time.Time
}

func (Grade) TableName() string { return "grades" }

type Schedule struct {
	ID        uint `gorm:"primaryKey"`
	ClassID   uint `gorm:"index"`
	SubjectID uint `gorm:"index"`
	TeacherID uint `gorm:"index"`
	DayOfWeek int
	Period    int
	Room      *string
	CreatedAt time.Time
	UpdatedAt time.Time
}

func (Schedule) TableName() string { return "schedules" }

type Teacher struct {
	ID          uint    `gorm:"primaryKey"`
	UserID      uint    `gorm:"uniqueIndex"`
	TeacherCode string  `gorm:"uniqueIndex"`
	Department  string
	Subjects    string
	HireDate    *time.Time `gorm:"type:date"`
	CreatedAt   time.Time
	UpdatedAt   time.Time
}

func (Teacher) TableName() string { return "teachers" }

type StudentGuardian struct {
	ID             uint `gorm:"primaryKey"`
	StudentID      uint `gorm:"index"`
	GuardianUserID uint `gorm:"column:guardian_user_id;index"`
	Relationship   string
	CreatedAt      time.Time
	UpdatedAt      time.Time
}

func (StudentGuardian) TableName() string { return "student_guardians" }
