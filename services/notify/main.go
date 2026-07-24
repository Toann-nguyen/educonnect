package main

import (
	"context"
	"encoding/json"
	"fmt"
	"log"
	"net/http"
	"net/smtp"
	"os"
	"time"

	"github.com/gin-gonic/gin"
	"github.com/redis/go-redis/v9"
)

var ctx = context.Background()

// ─── Notification Payload ─────────────────────────────────────────────────────

type NotifyPayload struct {
	Type    string `json:"type"`    // "email" | "sms"
	To      string `json:"to"`
	Subject string `json:"subject"`
	Body    string `json:"body"`
}

// ─── Email Sender ─────────────────────────────────────────────────────────────

func sendEmail(payload NotifyPayload) error {
	host := os.Getenv("MAIL_HOST")
	port := os.Getenv("MAIL_PORT")
	user := os.Getenv("MAIL_USERNAME")
	pass := os.Getenv("MAIL_PASSWORD")
	from := os.Getenv("MAIL_FROM")

	auth := smtp.PlainAuth("", user, pass, host)
	msg := []byte(fmt.Sprintf(
		"From: %s\r\nTo: %s\r\nSubject: %s\r\nMIME-version: 1.0;\r\nContent-Type: text/html; charset=\"UTF-8\";\r\n\r\n%s",
		from, payload.To, payload.Subject, payload.Body,
	))

	addr := fmt.Sprintf("%s:%s", host, port)
	return smtp.SendMail(addr, auth, from, []string{payload.To}, msg)
}

// ─── Redis Stream Consumer ────────────────────────────────────────────────────

func consumeStream(rdb *redis.Client) {
	streamKey := "notifications"
	groupName := "notify-service"
	consumerName := "notify-worker-1"

	// Tạo consumer group nếu chưa có
	rdb.XGroupCreateMkStream(ctx, streamKey, groupName, "$")

	log.Printf("Listening to Redis Stream '%s'...", streamKey)
	for {
		streams, err := rdb.XReadGroup(ctx, &redis.XReadGroupArgs{
			Group:    groupName,
			Consumer: consumerName,
			Streams:  []string{streamKey, ">"},
			Count:    10,
			Block:    5 * time.Second,
		}).Result()

		if err != nil && err != redis.Nil {
			log.Printf("Stream read error: %v", err)
			time.Sleep(2 * time.Second)
			continue
		}

		for _, stream := range streams {
			for _, msg := range stream.Messages {
				log.Printf("Processing message: %s", msg.ID)

				payloadJSON, ok := msg.Values["payload"].(string)
				if !ok {
					log.Printf("Invalid payload in message %s", msg.ID)
					rdb.XAck(ctx, streamKey, groupName, msg.ID)
					continue
				}

				var p NotifyPayload
				if err := json.Unmarshal([]byte(payloadJSON), &p); err != nil {
					log.Printf("Failed to parse payload: %v", err)
					rdb.XAck(ctx, streamKey, groupName, msg.ID)
					continue
				}

				switch p.Type {
				case "email":
					if err := sendEmail(p); err != nil {
						log.Printf("Failed to send email to %s: %v", p.To, err)
					} else {
						log.Printf("Email sent to %s", p.To)
					}
				default:
					log.Printf("Unknown notification type: %s", p.Type)
				}

				// Ack message sau khi xử lý
				rdb.XAck(ctx, streamKey, groupName, msg.ID)
			}
		}
	}
}

// ─── HTTP Health Server ───────────────────────────────────────────────────────

func setupHTTP(port string) {
	r := gin.Default()
	r.GET("/health", func(c *gin.Context) {
		c.JSON(http.StatusOK, gin.H{
			"service": "notify-service",
			"status":  "healthy",
			"mode":    "redis-stream-consumer",
		})
	})
	log.Printf("Notify HTTP health server on port %s", port)
	r.Run(fmt.Sprintf(":%s", port))
}

// ─── Main ─────────────────────────────────────────────────────────────────────

func main() {
	port := os.Getenv("PORT")
	if port == "" {
		port = "8081"
	}

	// Kết nối Redis
	rdb := redis.NewClient(&redis.Options{
		Addr:     fmt.Sprintf("%s:%s", os.Getenv("REDIS_HOST"), os.Getenv("REDIS_PORT")),
		Password: os.Getenv("REDIS_PASSWORD"),
		DB:       0,
	})

	if _, err := rdb.Ping(ctx).Result(); err != nil {
		log.Fatalf("Cannot connect to Redis: %v", err)
	}
	log.Println("Redis connected")

	// Chạy Redis Stream consumer trong goroutine
	go consumeStream(rdb)

	// HTTP server cho health check
	setupHTTP(port)
}
