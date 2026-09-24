package model

import (
	"time"

	"gorm.io/gorm"
)

type User struct {
	ID           uint   `gorm:"primaryKey"`
	Name         string `gorm:"size:255"`
	Email        string `gorm:"uniqueIndex;size:255"`
	Phone        string `gorm:"size:20"`
	AvatarURL    string `gorm:"size:255"`
	Password     string `gorm:"size:255"`
	PasswordHash string `gorm:"size:255"`
	Status       int    `gorm:"default:1"`
	IsActive     bool   `gorm:"default:true"`
	IsLocked     bool   `gorm:"default:false"`
	TokenVersion uint   `gorm:"default:1"`
	LastLoginAt  *time.Time
	CreatedAt    time.Time
	UpdatedAt    time.Time
	DeletedAt    gorm.DeletedAt `gorm:"index"`
}

func (User) TableName() string { return "users" }

func (u User) LastLoginAtValue() time.Time {
	if u.LastLoginAt == nil {
		return time.Time{}
	}
	return *u.LastLoginAt
}

type Role struct {
	ID        uint64 `gorm:"primaryKey"`
	Name      string `gorm:"size:255"`
	GuardName string `gorm:"size:255"`
}

func (Role) TableName() string { return "roles" }

type ModelHasRole struct {
	RoleID    uint64 `gorm:"column:role_id;primaryKey"`
	ModelType string `gorm:"column:model_type;primaryKey;size:255"`
	ModelID   uint64 `gorm:"column:model_id;primaryKey"`
}

func (ModelHasRole) TableName() string { return "model_has_roles" }
