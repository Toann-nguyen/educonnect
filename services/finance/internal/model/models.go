package model

import "time"

type FeeType struct {
	ID          uint      `json:"id" gorm:"primaryKey" example:"1"`
	Name        string    `json:"name" example:"Học phí"`
	Description string    `json:"description" example:"Học phí học kỳ 1"`
	Amount      float64   `json:"amount" example:"1000000"`
	IsActive    bool      `json:"is_active" example:"true"`
	CreatedAt   time.Time `json:"created_at"`
	UpdatedAt   time.Time `json:"updated_at"`
}

type Invoice struct {
	ID        uint       `json:"id" gorm:"primaryKey" example:"1"`
	StudentID uint       `json:"student_id" example:"10"`
	FeeTypeID uint       `json:"fee_type_id" example:"3"`
	Amount    float64    `json:"amount" example:"1000000"`
	Status    string     `json:"status" example:"pending"`
	DueDate   time.Time  `json:"due_date"`
	PaidAt    *time.Time `json:"paid_at,omitempty"`
	CreatedAt time.Time  `json:"created_at"`
	UpdatedAt time.Time  `json:"updated_at"`
}

type Payment struct {
	ID        uint      `json:"id" gorm:"primaryKey" example:"1"`
	InvoiceID uint      `json:"invoice_id" example:"1"`
	Amount    float64   `json:"amount" example:"1000000"`
	Method    string    `json:"method" example:"cash"`
	Note      string    `json:"note" example:"Thanh toán học phí"`
	PaidBy    uint      `json:"paid_by" example:"5"`
	CreatedAt time.Time `json:"created_at"`
	UpdatedAt time.Time `json:"updated_at"`
}

// UserReadModel — read model sync từ Identity qua user_events.
type UserReadModel struct {
	ID        uint      `json:"id" gorm:"primaryKey" example:"1"`
	Name      string    `json:"name" example:"Nguyễn Văn A"`
	Email     string    `json:"email" example:"a@example.com"`
	Roles     string    `json:"roles" example:"[\"student\"]"`
	IsActive  bool      `json:"is_active" example:"true"`
	CreatedAt time.Time `json:"created_at"`
	UpdatedAt time.Time `json:"updated_at"`
}

// TableName — cố định tên bảng (GORM mặc định pluralize thành user_read_models).
func (UserReadModel) TableName() string { return "users_read_model" }
