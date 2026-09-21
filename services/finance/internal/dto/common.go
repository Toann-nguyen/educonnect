package dto

// ErrorResponse — chuẩn lỗi trả về (message + code).
type ErrorResponse struct {
	Message string              `json:"message" example:"Bad request"`
	Errors  map[string][]string `json:"errors,omitempty"`
	Code    string              `json:"code,omitempty" example:"BAD_REQUEST"`
}
