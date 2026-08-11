package dto

import "educonnect/finance/internal/model"

type PaymentListResponse struct {
	Data []model.Payment `json:"data"`
}

type CreatePaymentRequest struct {
	InvoiceID uint    `json:"invoice_id" validate:"required,gt=0" example:"1"`
	Amount    float64 `json:"amount" validate:"required,gt=0" example:"1000000"`
	Method    string  `json:"method" validate:"required,oneof=cash bank_transfer vnpay" example:"cash"`
	Note      string  `json:"note" example:"Thanh toán học phí"`
	PaidBy    uint    `json:"paid_by" example:"5"`
}
