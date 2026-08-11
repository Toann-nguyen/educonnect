package handler

import (
	"time"

	"github.com/go-fuego/fuego"
	"gorm.io/gorm"

	"educonnect/finance/internal/dto"
	"educonnect/finance/internal/model"
)

type PaymentHandler struct {
	DB *gorm.DB
}

func (h PaymentHandler) List(c fuego.ContextNoBody) (dto.PaymentListResponse, error) {
	var payments []model.Payment
	if err := h.DB.Find(&payments).Error; err != nil {
		return dto.PaymentListResponse{}, fuego.InternalServerError{Title: "Failed to list payments", Err: err}
	}
	return dto.PaymentListResponse{Data: payments}, nil
}

func (h PaymentHandler) Create(c fuego.ContextWithBody[dto.CreatePaymentRequest]) (model.Payment, error) {
	body, err := c.Body()
	if err != nil {
		return model.Payment{}, err
	}
	p := model.Payment{
		InvoiceID: body.InvoiceID,
		Amount:    body.Amount,
		Method:    body.Method,
		Note:      body.Note,
		PaidBy:    body.PaidBy,
	}
	if err := h.DB.Create(&p).Error; err != nil {
		return model.Payment{}, fuego.InternalServerError{Title: "Failed to create payment", Err: err}
	}
	if err := h.DB.Model(&model.Invoice{}).Where("id = ?", body.InvoiceID).Updates(map[string]interface{}{
		"status":  "paid",
		"paid_at": time.Now(),
	}).Error; err != nil {
		return model.Payment{}, fuego.InternalServerError{Title: "Failed to mark invoice as paid", Err: err}
	}
	return p, nil
}
