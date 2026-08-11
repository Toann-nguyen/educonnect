package handler

import (
	"github.com/go-fuego/fuego"

	"educonnect/notify/internal/template"
)

type TemplateHandler struct {
	Store *template.Store
}

func (h TemplateHandler) List(c fuego.ContextNoBody) (templateListResponse, error) {
	items, err := h.Store.List(c.Context())
	if err != nil {
		return templateListResponse{}, fuego.InternalServerError{Title: "Failed to list templates", Err: err}
	}
	return templateListResponse{Data: items}, nil
}

func (h TemplateHandler) Get(c fuego.ContextNoBody) (*template.Template, error) {
	t, err := h.Store.Get(c.Context(), c.PathParam("name"))
	if err != nil {
		return nil, fuego.InternalServerError{Title: "Failed to get template", Err: err}
	}
	if t == nil {
		return nil, fuego.NotFoundError{Title: "Template not found"}
	}
	return t, nil
}

func (h TemplateHandler) Create(c fuego.ContextWithBody[template.Template]) (*template.Template, error) {
	body, err := c.Body()
	if err != nil {
		return nil, err
	}
	if err := h.Store.Save(c.Context(), &body); err != nil {
		return nil, fuego.InternalServerError{Title: "Failed to save template", Err: err}
	}
	return &body, nil
}

type templateListResponse struct {
	Data []template.Template `json:"data"`
}
