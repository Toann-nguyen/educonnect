package dto

import "educonnect/finance/internal/model"

type UserListResponse struct {
	Data []model.UserReadModel `json:"data"`
}
