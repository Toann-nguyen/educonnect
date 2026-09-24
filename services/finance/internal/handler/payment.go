package handler

import (
	"time"

	"github.com/gin-gonic/gin"
	"github.com/go-fuego/fuego"
	"gorm.io/gorm"

	"educonnect/finance/internal/auth"
	"educonnect/finance/internal/dto"
	"educonnect/finance/internal/model"
)

type PaymentHandler struct {
	DB *gorm.DB
}

func ginFromPaymentList(c fuego.ContextNoBody) *gin.Context {
	if gc, ok := c.Context().(*gin.Context); ok {
		return gc
	}
	return nil
}
func ginFromPaymentCreate[B any](c fuego.ContextWithBody[B]) *gin.Context {
	if gc, ok := c.Context().(*gin.Context); ok {
		return gc
	}
	return nil
}

func (h PaymentHandler) List(c fuego.ContextNoBody) (dto.PaymentListResponse, error) {
	gc := ginFromPaymentList(c)
	if gc != nil && !auth.HasPermissionGin(gc, auth.PermViewInvoices) && !auth.HasPermissionGin(gc, auth.PermManagePayments) && !auth.IsAllAccess(gc) {
		return dto.PaymentListResponse{}, fuego.ForbiddenError{Title: "Forbidden: insufficient permission", Detail: "view_invoices or manage_payments required"}
	}
	var payments []model.Payment
	if err := h.DB.Find(&payments).Error; err != nil {
		return dto.PaymentListResponse{}, fuego.InternalServerError{Title: "Failed to list payments", Err: err}
	}
	// ownership filter via invoice
	if gc != nil && !auth.IsAllAccess(gc) {
		filtered := make([]model.Payment, 0, len(payments))
		for i := range payments {
			if auth.CanAccessPayment(h.DB, gc, &payments[i]) {
				filtered = append(filtered, payments[i])
			}
		}
		payments = filtered
	}
	return dto.PaymentListResponse{Data: payments}, nil
}

func (h PaymentHandler) Create(c fuego.ContextWithBody[dto.CreatePaymentRequest]) (model.Payment, error) {
	gc := ginFromPaymentCreate(c)
	if gc != nil && !auth.HasPermissionGin(gc, auth.PermManagePayments) && !auth.IsAllAccess(gc) {
		return model.Payment{}, fuego.ForbiddenError{Title: "Forbidden: manage_payments required"}
	}
	body, err := c.Body()
	if err != nil {
		return model.Payment{}, err
	}
	// ownership: check invoice belongs to caller
	var inv model.Invoice
	if err := h.DB.First(&inv, body.InvoiceID).Error; err != nil {
		return model.Payment{}, fuego.NotFoundError{Title: "Invoice not found", Err: err}
	}
	if gc != nil && !auth.CanAccessInvoice(h.DB, gc, &inv) {
		return model.Payment{}, fuego.ForbiddenError{Title: "Forbidden: cannot pay invoice outside your scope"}
	}
	p2 := model.Payment{
		InvoiceID: body.InvoiceID,
		Amount:    body.Amount,
		Currency:  dto.NormalizeCurrency(body.Currency),
		Method:    body.Method,
		Note:      body.Note,
		PaidBy:    body.PaidBy,
	}
	if gc != nil && p2.PaidBy == 0 {
		p2.PaidBy = auth.GetUserID(gc)
	}
	if err := h.DB.Create(&p2).Error; err != nil {
		return model.Payment{}, fuego.InternalServerError{Title: "Failed to create payment", Err: err}
	}
	if err := h.DB.Model(&model.Invoice{}).Where("id = ?", body.InvoiceID).Updates(map[string]interface{}{
		"status":  "paid",
		"paid_at": time.Now(),
	}).Error; err != nil {
		return model.Payment{}, fuego.InternalServerError{Title: "Failed to mark invoice as paid", Err: err}
	}
	return p2, nil
}
