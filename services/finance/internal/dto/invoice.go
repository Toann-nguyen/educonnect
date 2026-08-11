package dto

import "educonnect/finance/internal/model"

// InvoiceListResponse — danh sách hoá đơn (data-only, giữ tương thích với API hiện tại).
type InvoiceListResponse struct {
	Data []model.Invoice `json:"data"`
}

type CreateInvoiceRequest struct {
	StudentID uint    `json:"student_id" validate:"required,gt=0" example:"10"`
	FeeTypeID uint    `json:"fee_type_id" validate:"required,gt=0" example:"3"`
	Amount    float64 `json:"amount" validate:"required,gt=0" example:"1000000"`
}

type UpdateInvoiceRequest struct {
	StudentID uint    `json:"student_id" validate:"gt=0" example:"10"`
	FeeTypeID uint    `json:"fee_type_id" validate:"gt=0" example:"3"`
	Amount    float64 `json:"amount" validate:"gt=0" example:"1000000"`
	Status    string  `json:"status" validate:"oneof=pending paid overdue cancelled" example:"paid"`
}
