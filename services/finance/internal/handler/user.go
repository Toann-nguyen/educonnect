package handler

import (
	"github.com/go-fuego/fuego"
	"gorm.io/gorm"

	"educonnect/finance/internal/dto"
	"educonnect/finance/internal/model"
)

type UserHandler struct {
	DB *gorm.DB
}

func (h UserHandler) List(c fuego.ContextNoBody) (dto.UserListResponse, error) {
	var users []model.UserReadModel
	if err := h.DB.Find(&users).Error; err != nil {
		return dto.UserListResponse{}, fuego.InternalServerError{Title: "Failed to list users", Err: err}
	}
	return dto.UserListResponse{Data: users}, nil
}
