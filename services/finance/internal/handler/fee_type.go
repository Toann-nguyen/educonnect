package handler

import (
	"github.com/gin-gonic/gin"
	"github.com/go-fuego/fuego"
	"gorm.io/gorm"

	"educonnect/finance/internal/auth"
	"educonnect/finance/internal/dto"
	"educonnect/finance/internal/model"
)

type FeeTypeHandler struct {
	DB *gorm.DB
}

func ginFromFee(c fuego.ContextNoBody) *gin.Context {
	if gc, ok := c.Context().(*gin.Context); ok {
		return gc
	}
	return nil
}
func ginFromFeeCreate[B any](c fuego.ContextWithBody[B]) *gin.Context {
	if gc, ok := c.Context().(*gin.Context); ok {
		return gc
	}
	return nil
}

func (h FeeTypeHandler) List(c fuego.ContextNoBody) (dto.FeeTypeListResponse, error) {
	gc := ginFromFee(c)
	if gc != nil && !auth.HasPermissionGin(gc, auth.PermViewInvoices) && !auth.HasPermissionGin(gc, auth.PermManageFinances) && !auth.IsAllAccess(gc) && !auth.HasRoleGin(gc, auth.RoleStudent, auth.RoleTeacher, auth.RoleHomeroom) {
		// allow any authenticated with at least view permission — students have view_invoices
		if !auth.HasPermissionGin(gc, auth.PermViewInvoices) {
			return dto.FeeTypeListResponse{}, fuego.ForbiddenError{Title: "Forbidden: insufficient permission"}
		}
	}
	var feeTypes []model.FeeType
	if err := h.DB.Find(&feeTypes).Error; err != nil {
		return dto.FeeTypeListResponse{}, fuego.InternalServerError{Title: "Failed to list fee types", Err: err}
	}
	return dto.FeeTypeListResponse{Data: feeTypes}, nil
}

func (h FeeTypeHandler) Create(c fuego.ContextWithBody[dto.CreateFeeTypeRequest]) (model.FeeType, error) {
	gc := ginFromFeeCreate(c)
	if gc != nil {
		if !auth.HasRoleGin(gc, auth.RolePrincipal, auth.RoleAdmin, auth.RoleAccountant) {
			return model.FeeType{}, fuego.ForbiddenError{Title: "Forbidden: only principal/admin/accountant can create fee types"}
		}
		if !auth.HasPermissionGin(gc, auth.PermManageFinances) && !auth.IsAllAccess(gc) {
			return model.FeeType{}, fuego.ForbiddenError{Title: "Forbidden: manage_finances required"}
		}
	}
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
