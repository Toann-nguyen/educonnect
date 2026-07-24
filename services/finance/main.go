package main

import (
	"fmt"
	"log"
	"net/http"
	"os"
	"strconv"
	"time"

	"github.com/gin-gonic/gin"
	"github.com/golang-jwt/jwt/v5"
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
	ID          uint      `json:"id" gorm:"primaryKey"`
	StudentID   uint      `json:"student_id"`
	FeeTypeID   uint      `json:"fee_type_id"`
	Amount      float64   `json:"amount"`
	Status      string    `json:"status"` // pending, paid, overdue, cancelled
	DueDate     time.Time `json:"due_date"`
	PaidAt      *time.Time `json:"paid_at,omitempty"`
	CreatedAt   time.Time `json:"created_at"`
	UpdatedAt   time.Time `json:"updated_at"`
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

// ─── JWT Middleware ───────────────────────────────────────────────────────────

func jwtMiddleware() gin.HandlerFunc {
	jwtSecret := os.Getenv("JWT_SECRET")
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
			if _, ok := t.Method.(*jwt.SigningMethodHMAC); !ok {
				return nil, fmt.Errorf("unexpected signing method: %v", t.Header["alg"])
			}
			return []byte(jwtSecret), nil
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
	}
}

// ─── Main ─────────────────────────────────────────────────────────────────────

func main() {
	port := os.Getenv("PORT")
	if port == "" {
		port = "8080"
	}

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

	// Auto-migrate tables
	db.AutoMigrate(&FeeType{}, &Invoice{}, &Payment{})
	log.Println("finance_db connected and migrated")

	r := gin.Default()
	setupRoutes(r, db)

	log.Printf("Finance Go Service starting on port %s...", port)
	if err := r.Run(fmt.Sprintf(":%s", port)); err != nil {
		log.Fatalf("Failed to run server: %v", err)
	}
}
