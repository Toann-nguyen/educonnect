package dto

import "educonnect/finance/internal/model"

type FeeTypeListResponse struct {
	Data []model.FeeType `json:"data"`
}

type CreateFeeTypeRequest struct {
	Name        string  `json:"name" validate:"required" example:"Học phí"`
	Description string  `json:"description" example:"Học phí học kỳ 1"`
	Amount      float64 `json:"amount" validate:"required,gt=0" example:"1000000"`
	Currency    string  `json:"currency" validate:"omitempty,len=3" example:"VND"`
	IsActive    bool    `json:"is_active" example:"true"`
}
