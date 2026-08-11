package router

import (
	"github.com/gin-gonic/gin"
	"github.com/go-fuego/fuego"
	"github.com/go-fuego/fuego/extra/fuegogin"
	"github.com/go-fuego/fuego/option"
	"gorm.io/gorm"

	"educonnect/finance/internal/auth"
	"educonnect/finance/internal/handler"
)

// Setup — đăng ký API /api/finance vào gin + spec OpenAPI vào engine.
func Setup(engine *fuego.Engine, ginRouter *gin.Engine, db *gorm.DB) {
	api := ginRouter.Group("/api/finance")
	api.Use(auth.JWT())

	feeType := handler.FeeTypeHandler{DB: db}
	fuegogin.Get(engine, api, "/fee-types", feeType.List,
		option.Tags("fee-types"), option.Summary("List fee types"))
	fuegogin.Post(engine, api, "/fee-types", feeType.Create,
		option.Tags("fee-types"), option.Summary("Create fee type"), option.DefaultStatusCode(201))

	invoice := handler.InvoiceHandler{DB: db}
	fuegogin.Get(engine, api, "/invoices", invoice.List,
		option.Tags("invoices"), option.Summary("List invoices"),
		option.QueryInt("student_id", "Filter by student ID"))
	fuegogin.Get(engine, api, "/invoices/:id", invoice.Get,
		option.Tags("invoices"), option.Summary("Get invoice by ID"))
	fuegogin.Post(engine, api, "/invoices", invoice.Create,
		option.Tags("invoices"), option.Summary("Create invoice"), option.DefaultStatusCode(201))
	fuegogin.Put(engine, api, "/invoices/:id", invoice.Update,
		option.Tags("invoices"), option.Summary("Update invoice"))

	payment := handler.PaymentHandler{DB: db}
	fuegogin.Get(engine, api, "/payments", payment.List,
		option.Tags("payments"), option.Summary("List payments"))
	fuegogin.Post(engine, api, "/payments", payment.Create,
		option.Tags("payments"), option.Summary("Create payment"), option.DefaultStatusCode(201))

	user := handler.UserHandler{DB: db}
	fuegogin.Get(engine, api, "/users", user.List,
		option.Tags("users"), option.Summary("List synced users"))

	// Đăng ký /docs/openapi.json + UI lên gin
	engine.RegisterOpenAPIRoutes(&fuegogin.OpenAPIHandler{GinEngine: ginRouter})
}
