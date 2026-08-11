package router

import (
	"github.com/gin-gonic/gin"
	"github.com/go-fuego/fuego"
	"github.com/go-fuego/fuego/extra/fuegogin"
	"github.com/go-fuego/fuego/option"

	"educonnect/notify/internal/handler"
	"educonnect/notify/internal/template"
)

// Setup — đăng ký API /api/notify vào gin + spec OpenAPI vào engine.
func Setup(engine *fuego.Engine, ginRouter *gin.Engine, store *template.Store) {
	h := handler.TemplateHandler{Store: store}

	api := ginRouter.Group("/api/notify")
	fuegogin.Get(engine, api, "/templates", h.List,
		option.Tags("templates"), option.Summary("List notification templates"))
	fuegogin.Get(engine, api, "/templates/:name", h.Get,
		option.Tags("templates"), option.Summary("Get template by name"))
	fuegogin.Post(engine, api, "/templates", h.Create,
		option.Tags("templates"), option.Summary("Create or update template"), option.DefaultStatusCode(201))

	engine.RegisterOpenAPIRoutes(&fuegogin.OpenAPIHandler{GinEngine: ginRouter})
}