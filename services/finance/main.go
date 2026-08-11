package main

import (
	"encoding/json"
	"fmt"
	"log"
	"net/http"
	"os"
	"time"

	"github.com/gin-gonic/gin"
	"github.com/getkin/kin-openapi/openapi3"
	"github.com/go-fuego/fuego"
	amqp "github.com/rabbitmq/amqp091-go"
	"gorm.io/driver/mysql"
	"gorm.io/gorm"

	"educonnect/finance/internal/auth"
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
	port := os.Getenv("PORT")
	if port == "" {
		port = "8080"
	}

	// JWT public key (RS256) — load 1 lần lúc start
	if err := auth.LoadJWTPublicKey(); err != nil {
		log.Fatalf("JWT public key: %v", err)
	}
	log.Println("JWT RS256 public key loaded")

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
	db.AutoMigrate(&model.FeeType{}, &model.Invoice{}, &model.Payment{}, &model.UserReadModel{})
	log.Println("finance_db connected and migrated")

	// User sync consumer (RabbitMQ — không block main)
	go startUserSyncConsumer(db)

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

	r.GET("/health", func(c *gin.Context) {
		c.JSON(http.StatusOK, gin.H{"service": "finance-service", "status": "healthy"})
	})

	log.Printf("Finance Go Service starting on port %s...", port)
	if err := r.Run(fmt.Sprintf(":%s", port)); err != nil {
		log.Fatalf("Failed to run server: %v", err)
	}
}
