package handler

import (
	"github.com/gin-gonic/gin"
	"github.com/go-fuego/fuego"

	"educonnect/notify/internal/auth"
	"educonnect/notify/internal/template"
)

type TemplateHandler struct {
	Store *template.Store
}

func ginFromNotify(c fuego.ContextNoBody) *gin.Context {
	if gc, ok := c.Context().(*gin.Context); ok {
		return gc
	}
	return nil
}
func ginFromNotifyBody[B any](c fuego.ContextWithBody[B]) *gin.Context {
	if gc, ok := c.Context().(*gin.Context); ok {
		return gc
	}
	return nil
}

func (h TemplateHandler) List(c fuego.ContextNoBody) (templateListResponse, error) {
	gc := ginFromNotify(c)
	if gc != nil && !auth.HasPermissionGin(gc, auth.PermViewNotify) && !auth.HasPermissionGin(gc, auth.PermManageNotify) && !auth.IsPrivileged(gc) {
		return templateListResponse{}, fuego.ForbiddenError{Title: "Forbidden: insufficient permission", Detail: "view_events required"}
	}
	items, err := h.Store.List(c.Context())
	if err != nil {
		return templateListResponse{}, fuego.InternalServerError{Title: "Failed to list templates", Err: err}
	}
	// ownership filter: teacher/homeroom chỉ thấy own, student thấy all (read-only)
	if gc != nil && auth.IsTeacherScopedNotify(gc) {
		uid := auth.GetUserID(gc)
		filtered := make([]template.Template, 0)
		for _, t := range items {
			if t.CreatedBy == 0 || t.CreatedBy == uid {
				filtered = append(filtered, t)
			}
		}
		items = filtered
	}
	return templateListResponse{Data: items}, nil
}

func (h TemplateHandler) Get(c fuego.ContextNoBody) (*template.Template, error) {
	gc := ginFromNotify(c)
	if gc != nil && !auth.HasPermissionGin(gc, auth.PermViewNotify) && !auth.IsPrivileged(gc) {
		return nil, fuego.ForbiddenError{Title: "Forbidden: insufficient permission"}
	}
	t, err := h.Store.Get(c.Context(), c.PathParam("name"))
	if err != nil {
		return nil, fuego.InternalServerError{Title: "Failed to get template", Err: err}
	}
	if t == nil {
		return nil, fuego.NotFoundError{Title: "Template not found"}
	}
	if gc != nil && !auth.CanAccessTemplate(gc, t) {
		return nil, fuego.ForbiddenError{Title: "Forbidden: ownership check failed", Detail: "Teacher chỉ template own, Principal all"}
	}
	return t, nil
}

func (h TemplateHandler) Create(c fuego.ContextWithBody[template.Template]) (*template.Template, error) {
	gc := ginFromNotifyBody(c)
	if gc != nil && !auth.HasPermissionGin(gc, auth.PermManageNotify) && !auth.IsPrivileged(gc) {
		return nil, fuego.ForbiddenError{Title: "Forbidden: manage_events required"}
	}
	body, err := c.Body()
	if err != nil {
		return nil, err
	}
	// if updating existing, check ownership
	if gc != nil {
		existing, _ := h.Store.Get(c.Context(), body.Name)
		if existing != nil && !auth.CanModifyTemplate(gc, existing) {
			return nil, fuego.ForbiddenError{Title: "Forbidden: cannot modify template owned by another user"}
		}
		// set ownership
		if body.CreatedBy == 0 {
			body.CreatedBy = auth.GetUserID(gc)
		} else if !auth.IsAllAccessNotify(gc) && body.CreatedBy != auth.GetUserID(gc) {
			// non-privileged cannot spoof owner
			body.CreatedBy = auth.GetUserID(gc)
		}
	}
	if err := h.Store.Save(c.Context(), &body); err != nil {
		return nil, fuego.InternalServerError{Title: "Failed to save template", Err: err}
	}
	return &body, nil
}

type templateListResponse struct {
	Data []template.Template `json:"data"`
}
