package handler

import (
	"time"

	"github.com/gin-gonic/gin"
	"github.com/go-fuego/fuego"
	"gorm.io/gorm"

	"educonnect/finance/internal/auth"
	"educonnect/finance/internal/model"
)

type StatsHandler struct {
	DB *gorm.DB
}

type StatsResponse struct {
	RevenueToday     float64 `json:"revenue_today" example:"1000000"`
	RevenueThisMonth float64 `json:"revenue_this_month" example:"5000000"`
	OverdueInvoices  int64   `json:"overdue_invoices" example:"12"`
}

func ginFromStats(c fuego.ContextNoBody) *gin.Context {
	if gc, ok := c.Context().(*gin.Context); ok {
		return gc
	}
	return nil
}

func (h StatsHandler) Get(c fuego.ContextNoBody) (StatsResponse, error) {
	gc := ginFromStats(c)
	if gc != nil && !auth.HasRoleGin(gc, auth.RolePrincipal, auth.RoleAdmin, auth.RoleAccountant) {
		return StatsResponse{}, fuego.ForbiddenError{Title: "Forbidden: only principal/admin/accountant can view stats"}
	}
	var revenueToday float64
	var revenueMonth float64
	var overdueCount int64

	today := time.Now().Format("2006-01-02")
	h.DB.Model(&model.Payment{}).Where("DATE(created_at) = ?", today).Select("COALESCE(SUM(amount),0)").Scan(&revenueToday)
	monthStart := time.Now().Format("2006-01")
	h.DB.Model(&model.Payment{}).Where("DATE_FORMAT(created_at, '%Y-%m') = ?", monthStart).Select("COALESCE(SUM(amount),0)").Scan(&revenueMonth)
	h.DB.Model(&model.Invoice{}).Where("status = ? OR (status IN (?,?) AND due_date < ?)", "overdue", "unpaid", "pending", today).Count(&overdueCount)

	return StatsResponse{
		RevenueToday:     revenueToday,
		RevenueThisMonth: revenueMonth,
		OverdueInvoices:  overdueCount,
	}, nil
}
