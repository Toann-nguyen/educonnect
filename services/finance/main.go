package main

import (
	"context"
	"encoding/json"
	"fmt"
	"log"
	"net/http"
	"os"
	"os/signal"
	"strconv"
	"syscall"
	"time"

	"github.com/getkin/kin-openapi/openapi3"
	"github.com/gin-gonic/gin"
	"github.com/go-fuego/fuego"
	amqp "github.com/rabbitmq/amqp091-go"
	"github.com/redis/go-redis/v9"
	"go.uber.org/zap"
	"gorm.io/driver/mysql"
	"gorm.io/gorm"

	"educonnect/finance/internal/auth"
	"educonnect/finance/internal/events"
	grpcserver "educonnect/finance/internal/grpc"
	"educonnect/finance/internal/model"
	"educonnect/finance/internal/router"
)

// ─── User Sync Consumer (Event-Driven — Task 3.2) ────────────────────────────

// UserEvent — payload chuẩn từ Identity (user_events exchange).
type UserEvent struct {
	Event         string        `json:"event"` // user.created | user.updated
	Version       int           `json:"version"`
	OccurredAt    time.Time     `json:"occurred_at"`
	CorrelationID string        `json:"correlation_id"`
	User          UserEventUser `json:"user"`
}

type UserEventUser struct {
	ID       uint     `json:"id"`
	Name     string   `json:"name"`
	Email    string   `json:"email"`
	Roles    []string `json:"roles"`
	IsActive bool     `json:"is_active"`
}

const (
	userEventsExchange = "user_events"
	financeUsersQueue  = "finance_users_sync"
)

// startUserSyncConsumer — consume user_events → upsert users_read_model.
func startUserSyncConsumer(db *gorm.DB) {
	url := os.Getenv("RABBITMQ_URL")
	if url == "" {
		url = "amqp://educonnect:educonnect_dev@rabbitmq:5672"
	}
	for {
		conn, err := amqp.Dial(url)
		if err != nil {
			log.Printf("RabbitMQ chưa sẵn sàng (retry 5s): %v", err)
			time.Sleep(5 * time.Second)
			continue
		}
		ch, err := conn.Channel()
		if err != nil {
			log.Printf("open channel: %v", err)
			conn.Close()
			time.Sleep(5 * time.Second)
			continue
		}
		if err := declareTopology(ch); err != nil {
			log.Printf("declare topology: %v", err)
			conn.Close()
			time.Sleep(5 * time.Second)
			continue
		}
		msgs, err := ch.Consume(financeUsersQueue, "finance-users-worker", false, false, false, false, nil)
		if err != nil {
			log.Printf("consume %s: %v", financeUsersQueue, err)
			conn.Close()
			time.Sleep(5 * time.Second)
			continue
		}
		log.Printf("📬 Finance user-sync sẵn sàng trên %s (user_events)", financeUsersQueue)
		for msg := range msgs {
			handleUserEvent(db, ch, msg)
		}
		log.Println("RabbitMQ connection closed — reconnect sau 5s")
		conn.Close()
		time.Sleep(5 * time.Second)
	}
}

func declareTopology(ch *amqp.Channel) error {
	if err := ch.ExchangeDeclare(userEventsExchange, "topic", true, false, false, false, nil); err != nil {
		return err
	}
	if _, err := ch.QueueDeclare(financeUsersQueue, true, false, false, false, nil); err != nil {
		return err
	}
	return ch.QueueBind(financeUsersQueue, "user.#", userEventsExchange, false, nil)
}

func handleUserEvent(db *gorm.DB, ch *amqp.Channel, msg amqp.Delivery) {
	var ev UserEvent
	if err := json.Unmarshal(msg.Body, &ev); err != nil {
		log.Printf("⚠️ user event payload lỗi: %v", err)
		ch.Nack(msg.DeliveryTag, false, false) // → DLX
		return
	}
	corrID := msg.Headers["x-correlation-id"]
	if corrID == "" {
		corrID = ev.CorrelationID
	}
	rolesJSON, _ := json.Marshal(ev.User.Roles)

	u := model.UserReadModel{
		ID:       ev.User.ID,
		Name:     ev.User.Name,
		Email:    ev.User.Email,
		Roles:    string(rolesJSON),
		IsActive: ev.User.IsActive,
	}
	var existing model.UserReadModel
	err := db.Where("id = ?", ev.User.ID).First(&existing).Error
	if err != nil {
		if err := db.Create(&u).Error; err != nil {
			log.Printf("❌ insert users_read_model id=%d: %v", ev.User.ID, err)
			ch.Nack(msg.DeliveryTag, false, false)
			return
		}
	} else {
		u.CreatedAt = existing.CreatedAt
		if err := db.Model(&model.UserReadModel{}).Where("id = ?", ev.User.ID).Updates(map[string]interface{}{
			"name":       ev.User.Name,
			"email":      ev.User.Email,
			"roles":      string(rolesJSON),
			"is_active":  ev.User.IsActive,
			"updated_at": time.Now(),
		}).Error; err != nil {
			log.Printf("❌ update users_read_model id=%d: %v", ev.User.ID, err)
			ch.Nack(msg.DeliveryTag, false, false)
			return
		}
	}
	ch.Ack(msg.DeliveryTag, false)
	log.Printf("[corr=%v] sync user.# → users_read_model id=%d (%s)", corrID, ev.User.ID, ev.User.Email)
}

// ─── Main ─────────────────────────────────────────────────────────────────────

func main() {
	// Setup logger
	logger, _ := zap.NewProduction()
	defer logger.Sync()

	httpPort := os.Getenv("PORT")
	if httpPort == "" {
		httpPort = "8080"
	}

	grpcPort := 8082
	if gp := os.Getenv("GRPC_PORT"); gp != "" {
		if port, err := strconv.Atoi(gp); err == nil {
			grpcPort = port
		}
	}

	// JWKS cache (RS256) — MicahParks/keyfunc, tự refresh 15m, kiểm tra iss/aud/exp/kid/tv/sid
	ctx := context.Background()
	auth.MustInitJWKS(ctx)
	defer auth.CloseJWKS()
	log.Println("JWKS RS256 cache ready")

	// Kết nối finance_db
	dsn := fmt.Sprintf("%s:%s@tcp(%s:%s)/%s?charset=utf8mb4&parseTime=True&loc=Local",
		os.Getenv("DB_FINANCE_USERNAME"),
		os.Getenv("DB_FINANCE_PASSWORD"),
		os.Getenv("DB_FINANCE_HOST"),
		os.Getenv("DB_FINANCE_PORT"),
		os.Getenv("DB_FINANCE_DATABASE"),
	)

	db, err := gorm.Open(mysql.Open(dsn), &gorm.Config{})
	if err != nil {
		log.Fatalf("Failed to connect to finance_db: %v", err)
	}

	// Auto-migrate tables (gồm users_read_model)
	db.AutoMigrate(&model.FeeType{}, &model.Invoice{}, &model.InvoiceItem{}, &model.Payment{}, &model.UserReadModel{})
	log.Println("finance_db connected and migrated")

	// User sync consumer (RabbitMQ — không block main)
	go startUserSyncConsumer(db)

	// T6.1: auth.events consumer — queue riêng finance_auth_events, DLQ, idempotency, invalidate permission cache
	redisHost := os.Getenv("REDIS_HOST")
	if redisHost == "" {
		redisHost = "redis"
	}
	redisPort := os.Getenv("REDIS_PORT")
	if redisPort == "" {
		redisPort = "6379"
	}
	rdb := redis.NewClient(&redis.Options{
		Addr:     fmt.Sprintf("%s:%s", redisHost, redisPort),
		Password: os.Getenv("REDIS_PASSWORD"),
	})
	go auth.StartAuthConsumer(context.Background(), rdb)

	r := gin.Default()

	engine := fuego.NewEngine(fuego.WithOpenAPIConfig(fuego.OpenAPIConfig{
		SpecURL:          "/docs/openapi.json",
		DisableSwaggerUI: true,
		Info: &openapi3.Info{
			Title:       "Finance API",
			Version:     "1.0.0",
			Description: "Finance microservice — quản lý hoá đơn, loại phí và thanh toán.",
		},
	}))
	engine.OpenAPI.Description().Servers = []*openapi3.Server{{URL: "/api/finance"}}

	router.Setup(engine, r, db)

	// Stoplight Elements UI — đăng ký trực tiếp trên gin tại cả /docs và /docs/
	// để tránh redirect trailing-slash của gin khi truy cập qua nginx.
	// Spec URL tương đối (./openapi.json) để nginx proxy /docs/finance/ → /docs/.
	openapiUI := func(c *gin.Context) {
		c.Data(http.StatusOK, "text/html; charset=utf-8", []byte(fuego.DefaultOpenAPIHTML("openapi.json")))
	}
	r.GET("/docs", openapiUI)
	r.GET("/docs/", openapiUI)

	r.GET("/health", func(c *gin.Context) {
		c.JSON(http.StatusOK, gin.H{"service": "finance-service", "status": "healthy"})
	})

	// ─── Start HTTP Server ─────────────────────────────────────────────────────
	httpServer := &http.Server{
		Addr:    fmt.Sprintf(":%s", httpPort),
		Handler: r,
	}

	go func() {
		log.Printf("HTTP Finance Go Service starting on port %s...", httpPort)
		if err := httpServer.ListenAndServe(); err != nil && err != http.ErrServerClosed {
			log.Fatalf("Failed to run HTTP server: %v", err)
		}
	}()

	// ─── Start gRPC Server ─────────────────────────────────────────────────────
	grpcServerConfig := grpcserver.Config{
		Port:           grpcPort,
		MaxRecvMsgSize: 10 << 20, // 10 MB
		MaxSendMsgSize: 10 << 20, // 10 MB
	}

	publisher := events.NewAMQPPublisher(os.Getenv("RABBITMQ_URL"))
	grpcServer := grpcserver.NewServer(grpcServerConfig, logger)
	grpcServer.RegisterServices(db, publisher)

	go func() {
		if err := grpcServer.Start(); err != nil {
			log.Fatalf("Failed to run gRPC server: %v", err)
		}
	}()

	log.Printf("Finance Service started - HTTP: :%s, gRPC: :%d", httpPort, grpcServerConfig.Port)

	// ─── Graceful Shutdown ─────────────────────────────────────────────────────
	quit := make(chan os.Signal, 1)
	signal.Notify(quit, syscall.SIGINT, syscall.SIGTERM)
	<-quit

	log.Println("Shutting down servers...")

	ctxShutdown, cancel := context.WithTimeout(context.Background(), 30*time.Second)
	defer cancel()

	// Shutdown HTTP server
	if err := httpServer.Shutdown(ctxShutdown); err != nil {
		log.Printf("HTTP server shutdown error: %v", err)
	}

	// Shutdown gRPC server
	grpcServer.Stop()

	log.Println("Finance Service stopped")
}
