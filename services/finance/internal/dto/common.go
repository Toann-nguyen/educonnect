package dto

import "strings"

// NormalizeCurrency — mã tiền tệ ISO 4217, mặc định VND khi bỏ trống.
func NormalizeCurrency(currency string) string {
	currency = strings.ToUpper(strings.TrimSpace(currency))
	if currency == "" {
		return "VND"
	}
	return currency
}

// ErrorResponse — chuẩn lỗi trả về (message + code).
type ErrorResponse struct {
	Message string              `json:"message" example:"Bad request"`
	Errors  map[string][]string `json:"errors,omitempty"`
	Code    string              `json:"code,omitempty" example:"BAD_REQUEST"`
}
