package handler

import (
	"time"

	"github.com/go-fuego/fuego"
	"gorm.io/gorm"

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

func (h StatsHandler) Get(c fuego.ContextNoBody) (StatsResponse, error) {
	var revenueToday float64
	var revenueMonth float64
	var overdueCount int64

	// Revenue today — SUM payments where DATE(created_at) = today
	// Finance service dùng CreatedAt cho payment (không có payment_date riêng)
	today := time.Now().Format("2006-01-02")
	h.DB.Model(&model.Payment{}).Where("DATE(created_at) = ?", today).Select("COALESCE(SUM(amount),0)").Scan(&revenueToday)
	// Revenue this month
	monthStart := time.Now().Format("2006-01")
	// MySQL: DATE_FORMAT(created_at, '%Y-%m') = monthStart
	h.DB.Model(&model.Payment{}).Where("DATE_FORMAT(created_at, '%Y-%m') = ?", monthStart).Select("COALESCE(SUM(amount),0)").Scan(&revenueMonth)

	// Overdue invoices: status='overdue' OR (status='unpaid' OR 'pending' AND due_date < today)
	// Giữ tương thích với DashBoardService cũ: overdue + unpaid past due
	h.DB.Model(&model.Invoice{}).Where("status = ? OR (status IN (?,?) AND due_date < ?)", "overdue", "unpaid", "pending", today).Count(&overdueCount)

	return StatsResponse{
		RevenueToday:     revenueToday,
		RevenueThisMonth: revenueMonth,
		OverdueInvoices:  overdueCount,
	}, nil
}
