package main

import (
	"fmt"
	"log"
	"net/http"
	"os"

	"github.com/gin-gonic/gin"
)

func main() {
	port := os.Getenv("PORT")
	if port == "" {
		port = "8081"
	}

	r := gin.Default()

	r.GET("/health", func(c *gin.Context) {
		c.JSON(http.StatusOK, gin.H{
			"service": "notify-service",
			"status":  "healthy",
		})
	})

	api := r.Group("/api/notify")
	{
		api.POST("/send-email", func(c *gin.Context) {
			c.JSON(http.StatusOK, gin.H{
				"message": "Email notification queued via Go Notify Service",
			})
		})

		api.POST("/send-sms", func(c *gin.Context) {
			c.JSON(http.StatusOK, gin.H{
				"message": "SMS notification queued via Go Notify Service",
			})
		})
	}

	log.Printf("Notify Go Service starting on port %s...", port)
	if err := r.Run(fmt.Sprintf(":%s", port)); err != nil {
		log.Fatalf("Failed to run server: %v", err)
	}
}
