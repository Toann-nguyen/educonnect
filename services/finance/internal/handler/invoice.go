package handler

import (
	"errors"

	"github.com/go-fuego/fuego"
	"gorm.io/gorm"

	"educonnect/finance/internal/dto"
	"educonnect/finance/internal/model"
)

type InvoiceHandler struct {
	DB *gorm.DB
}

func (h InvoiceHandler) List(c fuego.ContextNoBody) (dto.InvoiceListResponse, error) {
	db := h.DB
	if studentID := c.QueryParamInt("student_id"); studentID > 0 {
		db = db.Where("student_id = ?", studentID)
	}
	var invoices []model.Invoice
	if err := db.Find(&invoices).Error; err != nil {
		return dto.InvoiceListResponse{}, fuego.InternalServerError{Title: "Failed to list invoices", Err: err}
	}
	return dto.InvoiceListResponse{Data: invoices}, nil
}

func (h InvoiceHandler) Get(c fuego.ContextNoBody) (model.Invoice, error) {
	var inv model.Invoice
	if err := h.DB.First(&inv, c.PathParamInt("id")).Error; err != nil {
		if errors.Is(err, gorm.ErrRecordNotFound) {
			return model.Invoice{}, fuego.NotFoundError{Title: "Invoice not found", Err: err}
		}
		return model.Invoice{}, fuego.InternalServerError{Title: "Failed to get invoice", Err: err}
	}
	return inv, nil
}

func (h InvoiceHandler) Create(c fuego.ContextWithBody[dto.CreateInvoiceRequest]) (model.Invoice, error) {
	body, err := c.Body()
	if err != nil {
		return model.Invoice{}, err
	}
	inv := model.Invoice{
		StudentID: body.StudentID,
		FeeTypeID: body.FeeTypeID,
		Amount:    body.Amount,
		Status:    "pending",
	}
	if err := h.DB.Create(&inv).Error; err != nil {
		return model.Invoice{}, fuego.InternalServerError{Title: "Failed to create invoice", Err: err}
	}
	return inv, nil
}

func (h InvoiceHandler) Update(c fuego.ContextWithBody[dto.UpdateInvoiceRequest]) (model.Invoice, error) {
	id := c.PathParamInt("id")
	var inv model.Invoice
	if err := h.DB.First(&inv, id).Error; err != nil {
		if errors.Is(err, gorm.ErrRecordNotFound) {
			return model.Invoice{}, fuego.NotFoundError{Title: "Invoice not found", Err: err}
		}
		return model.Invoice{}, fuego.InternalServerError{Title: "Failed to get invoice", Err: err}
	}

	body, err := c.Body()
	if err != nil {
		return model.Invoice{}, err
	}
	if body.StudentID > 0 {
		inv.StudentID = body.StudentID
	}
	if body.FeeTypeID > 0 {
		inv.FeeTypeID = body.FeeTypeID
	}
	if body.Amount > 0 {
		inv.Amount = body.Amount
	}
	if body.Status != "" {
		inv.Status = body.Status
	}

	if err := h.DB.Save(&inv).Error; err != nil {
		return model.Invoice{}, fuego.InternalServerError{Title: "Failed to update invoice", Err: err}
	}
	return inv, nil
}
