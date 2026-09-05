package handler

import (
	"errors"

	"github.com/gin-gonic/gin"
	"github.com/go-fuego/fuego"
	"gorm.io/gorm"

	"educonnect/finance/internal/auth"
	"educonnect/finance/internal/dto"
	"educonnect/finance/internal/model"
)

type InvoiceHandler struct {
	DB *gorm.DB
}

func ginFromFuego(c fuego.ContextNoBody) *gin.Context {
	if gc, ok := c.Context().(*gin.Context); ok {
		return gc
	}
	return nil
}
func ginFromFuegoBody[B any](c fuego.ContextWithBody[B]) *gin.Context {
	if gc, ok := c.Context().(*gin.Context); ok {
		return gc
	}
	return nil
}

func (h InvoiceHandler) List(c fuego.ContextNoBody) (dto.InvoiceListResponse, error) {
	gc := ginFromFuego(c)
	if gc != nil {
		if !auth.HasPermissionGin(gc, auth.PermViewInvoices) && !auth.HasPermissionGin(gc, auth.PermManageInvoices) && !auth.IsAllAccess(gc) {
			return dto.InvoiceListResponse{}, fuego.ForbiddenError{Title: "Forbidden: insufficient permission", Detail: "view_invoices required"}
		}
	}
	db := h.DB
	if gc != nil {
		db = auth.FilterInvoices(db, gc)
	}
	if studentID := c.QueryParamInt("student_id"); studentID > 0 {
		db = db.Where("student_id = ?", studentID)
	}
	var invoices []model.Invoice
	if err := db.Find(&invoices).Error; err != nil {
		return dto.InvoiceListResponse{}, fuego.InternalServerError{Title: "Failed to list invoices", Err: err}
	}
	// post-filter ownership for teacher/student (defense in depth — FilterInvoices already applied, but double-check per-row)
	if gc != nil && !auth.IsAllAccess(gc) {
		filtered := make([]model.Invoice, 0, len(invoices))
		for i := range invoices {
			if auth.CanAccessInvoice(h.DB, gc, &invoices[i]) {
				filtered = append(filtered, invoices[i])
			}
		}
		invoices = filtered
	}
	return dto.InvoiceListResponse{Data: invoices}, nil
}

func (h InvoiceHandler) Get(c fuego.ContextNoBody) (model.Invoice, error) {
	gc := ginFromFuego(c)
	if gc != nil && !auth.HasPermissionGin(gc, auth.PermViewInvoices) && !auth.HasPermissionGin(gc, auth.PermManageInvoices) && !auth.IsAllAccess(gc) {
		return model.Invoice{}, fuego.ForbiddenError{Title: "Forbidden: insufficient permission"}
	}
	var inv model.Invoice
	if err := h.DB.First(&inv, c.PathParamInt("id")).Error; err != nil {
		if errors.Is(err, gorm.ErrRecordNotFound) {
			return model.Invoice{}, fuego.NotFoundError{Title: "Invoice not found", Err: err}
		}
		return model.Invoice{}, fuego.InternalServerError{Title: "Failed to get invoice", Err: err}
	}
	if gc != nil && !auth.CanAccessInvoice(h.DB, gc, &inv) {
		return model.Invoice{}, fuego.ForbiddenError{Title: "Forbidden: ownership check failed", Detail: "Teacher chỉ lớp assigned, Student own, Principal all"}
	}
	return inv, nil
}

func (h InvoiceHandler) Create(c fuego.ContextWithBody[dto.CreateInvoiceRequest]) (model.Invoice, error) {
	gc := ginFromFuegoBody(c)
	if gc != nil && !auth.HasPermissionGin(gc, auth.PermManageInvoices) && !auth.IsAllAccess(gc) {
		return model.Invoice{}, fuego.ForbiddenError{Title: "Forbidden: manage_invoices required"}
	}
	body, err := c.Body()
	if err != nil {
		return model.Invoice{}, err
	}
	// ownership: Teacher chỉ được tạo invoice cho học sinh thuộc lớp assigned
	if gc != nil && auth.IsTeacherScoped(gc) {
		tmp := model.Invoice{StudentID: body.StudentID}
		if !auth.CanAccessInvoice(h.DB, gc, &tmp) {
			return model.Invoice{}, fuego.ForbiddenError{Title: "Forbidden: cannot create invoice for student outside your class"}
		}
	}
	if gc != nil && auth.IsStudent(gc) {
		// Student không được tạo invoice cho người khác
		if body.StudentID != auth.GetUserID(gc) {
			return model.Invoice{}, fuego.ForbiddenError{Title: "Forbidden: students can only create own invoices"}
		}
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
	gc := ginFromFuegoBody(c)
	if gc != nil && !auth.HasPermissionGin(gc, auth.PermManageInvoices) && !auth.IsAllAccess(gc) {
		return model.Invoice{}, fuego.ForbiddenError{Title: "Forbidden: manage_invoices required"}
	}
	id := c.PathParamInt("id")
	var inv model.Invoice
	if err := h.DB.First(&inv, id).Error; err != nil {
		if errors.Is(err, gorm.ErrRecordNotFound) {
			return model.Invoice{}, fuego.NotFoundError{Title: "Invoice not found", Err: err}
		}
		return model.Invoice{}, fuego.InternalServerError{Title: "Failed to get invoice", Err: err}
	}
	if gc != nil && !auth.CanAccessInvoice(h.DB, gc, &inv) {
		return model.Invoice{}, fuego.ForbiddenError{Title: "Forbidden: ownership check failed"}
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
