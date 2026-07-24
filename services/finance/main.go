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
		port = "8080"
	}

	r := gin.Default()

	r.GET("/health", func(c *gin.Context) {
		c.JSON(http.StatusOK, gin.H{
			"service": "finance-service",
			"status":  "healthy",
		})
	})

	api := r.Group("/api/finance")
	{
		api.GET("/invoices", func(c *gin.Context) {
			c.JSON(http.StatusOK, gin.H{
				"message": "List invoices from Go Finance Service",
				"data":    []interface{}{},
			})
		})

		api.GET("/payments", func(c *gin.Context) {
			c.JSON(http.StatusOK, gin.H{
				"message": "List payments from Go Finance Service",
				"data":    []interface{}{},
			})
		})
	}

	log.Printf("Finance Go Service starting on port %s...", port)
	if err := r.Run(fmt.Sprintf(":%s", port)); err != nil {
		log.Fatalf("Failed to run server: %v", err)
	}
}
