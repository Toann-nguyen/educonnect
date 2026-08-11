package handler

import (
	"github.com/go-fuego/fuego"
	"gorm.io/gorm"

	"educonnect/finance/internal/dto"
	"educonnect/finance/internal/model"
)

type FeeTypeHandler struct {
	DB *gorm.DB
}

func (h FeeTypeHandler) List(c fuego.ContextNoBody) (dto.FeeTypeListResponse, error) {
	var feeTypes []model.FeeType
	if err := h.DB.Find(&feeTypes).Error; err != nil {
		return dto.FeeTypeListResponse{}, fuego.InternalServerError{Title: "Failed to list fee types", Err: err}
	}
	return dto.FeeTypeListResponse{Data: feeTypes}, nil
}

func (h FeeTypeHandler) Create(c fuego.ContextWithBody[dto.CreateFeeTypeRequest]) (model.FeeType, error) {
	body, err := c.Body()
	if err != nil {
		return model.FeeType{}, err
	}
	ft := model.FeeType{
		Name:        body.Name,
		Description: body.Description,
		Amount:      body.Amount,
		IsActive:    body.IsActive,
	}
	if err := h.DB.Create(&ft).Error; err != nil {
		return model.FeeType{}, fuego.InternalServerError{Title: "Failed to create fee type", Err: err}
	}
	return ft, nil
}
