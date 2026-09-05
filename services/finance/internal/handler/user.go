package handler

import (
	"github.com/gin-gonic/gin"
	"github.com/go-fuego/fuego"
	"gorm.io/gorm"

	"educonnect/finance/internal/auth"
	"educonnect/finance/internal/dto"
	"educonnect/finance/internal/model"
)

type UserHandler struct {
	DB *gorm.DB
}

func ginFromUser(c fuego.ContextNoBody) *gin.Context {
	if gc, ok := c.Context().(*gin.Context); ok {
		return gc
	}
	return nil
}

func (h UserHandler) List(c fuego.ContextNoBody) (dto.UserListResponse, error) {
	gc := ginFromUser(c)
	if gc != nil && !auth.HasPermissionGin(gc, auth.PermViewUsers) && !auth.IsAllAccess(gc) {
		return dto.UserListResponse{}, fuego.ForbiddenError{Title: "Forbidden: view_users required"}
	}
	var users []model.UserReadModel
	if err := h.DB.Find(&users).Error; err != nil {
		return dto.UserListResponse{}, fuego.InternalServerError{Title: "Failed to list users", Err: err}
	}
	return dto.UserListResponse{Data: users}, nil
}
