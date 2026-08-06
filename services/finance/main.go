package main

import (
	"crypto/rsa"
	"encoding/json"
	"fmt"
	"log"
	"net/http"
	"os"
	"strconv"
	"time"

	"github.com/gin-gonic/gin"
	"github.com/golang-jwt/jwt/v5"
	amqp "github.com/rabbitmq/amqp091-go"
	"gorm.io/driver/mysql"
	"gorm.io/gorm"
)

// ─── Models ──────────────────────────────────────────────────────────────────

type FeeType struct {
	ID          uint      `json:"id" gorm:"primaryKey"`
	Name        string    `json:"name"`
	Description string    `json:"description"`
	Amount      float64   `json:"amount"`
	IsActive    bool      `json:"is_active"`
	CreatedAt   time.Time `json:"created_at"`
	UpdatedAt   time.Time `json:"updated_at"`
}

type Invoice struct {
	ID        uint       `json:"id" gorm:"primaryKey"`
	StudentID uint       `json:"student_id"`
	FeeTypeID uint       `json:"fee_type_id"`
	Amount    float64    `json:"amount"`
	Status    string     `json:"status"` // pending, paid, overdue, cancelled
	DueDate   time.Time  `json:"due_date"`
	PaidAt    *time.Time `json:"paid_at,omitempty"`
	CreatedAt time.Time  `json:"created_at"`
	UpdatedAt time.Time  `json:"updated_at"`
}

type Payment struct {
	ID        uint      `json:"id" gorm:"primaryKey"`
	InvoiceID uint      `json:"invoice_id"`
	Amount    float64   `json:"amount"`
	Method    string    `json:"method"` // cash, bank_transfer, vnpay
	Note      string    `json:"note"`
	PaidBy    uint      `json:"paid_by"` // user_id từ identity_db (tham chiếu)
	CreatedAt time.Time `json:"created_at"`
	UpdatedAt time.Time `json:"updated_at"`
}

// UserReadModel — bảng bóng (read model) sync từ Identity qua user_events
type UserReadModel struct {
	ID        uint      `json:"id" gorm:"primaryKey"`
	Name      string    `json:"name"`
	Email     string    `json:"email"`
	Roles     string    `json:"roles"` // JSON array string, vd: ["student"]
	IsActive  bool      `json:"is_active"`
	CreatedAt time.Time `json:"created_at"`
	UpdatedAt time.Time `json:"updated_at"`
}

// TableName — cố định tên bảng (GORM mặc định pluralize thành user_read_models)
func (UserReadModel) TableName() string { return "users_read_model" }

// ─── JWT Middleware (RS256 — verify bằng public key của Identity) ────────────

var jwtPublicKey *rsa.PublicKey

func loadJWTPublicKey() error {
	pemPath := os.Getenv("JWT_PUBLIC_KEY_PATH")
	if pemPath == "" {
		pemPath = "/certs/jwt-rsa-4096-public.pem"
	}
	pemBytes, err := os.ReadFile(pemPath)
	if err != nil {
		return fmt.Errorf("read public key %s: %w", pemPath, err)
	}
	jwtPublicKey, err = jwt.ParseRSAPublicKeyFromPEM(pemBytes)
	if err != nil {
		return fmt.Errorf("parse public key: %w", err)
	}
	return nil
}

func jwtMiddleware() gin.HandlerFunc {
	return func(c *gin.Context) {
		tokenStr := c.GetHeader("Authorization")
		if tokenStr == "" {
			c.JSON(http.StatusUnauthorized, gin.H{"message": "Authorization header required"})
			c.Abort()
			return
		}
		// Strip "Bearer "
		if len(tokenStr) > 7 && tokenStr[:7] == "Bearer " {
			tokenStr = tokenStr[7:]
		}

		token, err := jwt.Parse(tokenStr, func(t *jwt.Token) (interface{}, error) {
			if _, ok := t.Method.(*jwt.SigningMethodRSA); !ok {
				return nil, fmt.Errorf("unexpected signing method: %v", t.Header["alg"])
			}
			return jwtPublicKey, nil
		})
		if err != nil || !token.Valid {
			c.JSON(http.StatusUnauthorized, gin.H{"message": "Invalid or expired token"})
			c.Abort()
			return
		}

		if claims, ok := token.Claims.(jwt.MapClaims); ok {
			c.Set("user_id", claims["sub"])
		}
		c.Next()
	}
}

// ─── User Sync Consumer (Event-Driven — Task 3.2) ────────────────────────────

// UserEvent — payload chuẩn từ Identity (user_events exchange)
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

// startUserSyncConsumer — consume user_events → upsert users_read_model
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

	u := UserReadModel{
		ID:       ev.User.ID,
		Name:     ev.User.Name,
		Email:    ev.User.Email,
		Roles:    string(rolesJSON),
		IsActive: ev.User.IsActive,
	}
	var existing UserReadModel
	err := db.Where("id = ?", ev.User.ID).First(&existing).Error
	if err != nil {
		// chưa có → insert
		if err := db.Create(&u).Error; err != nil {
			log.Printf("❌ insert users_read_model id=%d: %v", ev.User.ID, err)
			ch.Nack(msg.DeliveryTag, false, false)
			return
		}
	} else {
		u.CreatedAt = existing.CreatedAt
		if err := db.Model(&UserReadModel{}).Where("id = ?", ev.User.ID).Updates(map[string]interface{}{
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

// ─── Handlers ────────────────────────────────────────────────────────────────

func setupRoutes(r *gin.Engine, db *gorm.DB) {
	r.GET("/health", func(c *gin.Context) {
		c.JSON(http.StatusOK, gin.H{"service": "finance-service", "status": "healthy"})
	})

	api := r.Group("/api/finance").Use(jwtMiddleware())
	{
		// FeeType
		api.GET("/fee-types", func(c *gin.Context) {
			var feeTypes []FeeType
			db.Find(&feeTypes)
			c.JSON(http.StatusOK, gin.H{"data": feeTypes})
		})
		api.POST("/fee-types", func(c *gin.Context) {
			var ft FeeType
			if err := c.ShouldBindJSON(&ft); err != nil {
				c.JSON(http.StatusBadRequest, gin.H{"message": err.Error()})
				return
			}
			db.Create(&ft)
			c.JSON(http.StatusCreated, gin.H{"data": ft})
		})

		// Invoice
		api.GET("/invoices", func(c *gin.Context) {
			var invoices []Invoice
			db.Find(&invoices)
			c.JSON(http.StatusOK, gin.H{"data": invoices})
		})
		api.GET("/invoices/:id", func(c *gin.Context) {
			id, _ := strconv.Atoi(c.Param("id"))
			var inv Invoice
			if err := db.First(&inv, id).Error; err != nil {
				c.JSON(http.StatusNotFound, gin.H{"message": "Invoice not found"})
				return
			}
			c.JSON(http.StatusOK, gin.H{"data": inv})
		})
		api.POST("/invoices", func(c *gin.Context) {
			var inv Invoice
			if err := c.ShouldBindJSON(&inv); err != nil {
				c.JSON(http.StatusBadRequest, gin.H{"message": err.Error()})
				return
			}
			inv.Status = "pending"
			db.Create(&inv)
			c.JSON(http.StatusCreated, gin.H{"data": inv})
		})
		api.PUT("/invoices/:id", func(c *gin.Context) {
			id, _ := strconv.Atoi(c.Param("id"))
			var inv Invoice
			if err := db.First(&inv, id).Error; err != nil {
				c.JSON(http.StatusNotFound, gin.H{"message": "Invoice not found"})
				return
			}
			c.ShouldBindJSON(&inv)
			db.Save(&inv)
			c.JSON(http.StatusOK, gin.H{"data": inv})
		})

		// Payment
		api.GET("/payments", func(c *gin.Context) {
			var payments []Payment
			db.Find(&payments)
			c.JSON(http.StatusOK, gin.H{"data": payments})
		})
		api.POST("/payments", func(c *gin.Context) {
			var p Payment
			if err := c.ShouldBindJSON(&p); err != nil {
				c.JSON(http.StatusBadRequest, gin.H{"message": err.Error()})
				return
			}
			// Cập nhật invoice sang paid
			db.Create(&p)
			db.Model(&Invoice{}).Where("id = ?", p.InvoiceID).Updates(map[string]interface{}{
				"status":  "paid",
				"paid_at": time.Now(),
			})
			c.JSON(http.StatusCreated, gin.H{"data": p})
		})

		// Users read model (Task 3.2 — sync từ Identity)
		api.GET("/users", func(c *gin.Context) {
			var users []UserReadModel
			db.Find(&users)
			c.JSON(http.StatusOK, gin.H{"data": users})
		})
	}
}

// ─── Main ─────────────────────────────────────────────────────────────────────

func main() {
	port := os.Getenv("PORT")
	if port == "" {
		port = "8080"
	}

	// JWT public key (RS256) — load 1 lần lúc start
	if err := loadJWTPublicKey(); err != nil {
		log.Fatalf("JWT public key: %v", err)
	}
	log.Println("JWT RS256 public key loaded")

	// Kết nối finance_db
	dsn := fmt.Sprintf("%s:%s@tcp(%s:%s)/%s?charset=utf8mb4&parseTime=True&loc=Local",
		os.Getenv("DB_USERNAME"),
		os.Getenv("DB_PASSWORD"),
		os.Getenv("DB_HOST"),
		os.Getenv("DB_PORT"),
		os.Getenv("DB_DATABASE"),
	)

	db, err := gorm.Open(mysql.Open(dsn), &gorm.Config{})
	if err != nil {
		log.Fatalf("Failed to connect to finance_db: %v", err)
	}

	// Auto-migrate tables (gồm users_read_model)
	db.AutoMigrate(&FeeType{}, &Invoice{}, &Payment{}, &UserReadModel{})
	log.Println("finance_db connected and migrated")

	// User sync consumer (RabbitMQ — không block main)
	go startUserSyncConsumer(db)

	r := gin.Default()
	setupRoutes(r, db)

	log.Printf("Finance Go Service starting on port %s...", port)
	if err := r.Run(fmt.Sprintf(":%s", port)); err != nil {
		log.Fatalf("Failed to run server: %v", err)
	}
}
