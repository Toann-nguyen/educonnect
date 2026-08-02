package main

import (
	"encoding/json"
	"fmt"
	"log"
	"net/http"
	"net/smtp"
	"os"
	"time"

	"github.com/gin-gonic/gin"
	amqp "github.com/rabbitmq/amqp091-go"
)

// ─── Broker topology (khớp docker/rabbitmq/definitions.json) ──

const (
	exchangeUser    = "user_events"
	exchangeFinance = "finance_events"
	queueNotify     = "notification_queue"
)

// ─── Notification Payload ─────────────────────────────────────

type NotifyPayload struct {
	Type    string `json:"type"` // "email" | "sms"
	To      string `json:"to"`
	Subject string `json:"subject"`
	Body    string `json:"body"`
	UserID  uint   `json:"user_id"` // để realtime route theo user
}

// ─── RabbitMQ connection (retry chờ broker khởi động) ─────────

func rabbitURL() string {
	return fmt.Sprintf("amqp://%s:%s@%s:%s/",
		os.Getenv("RABBITMQ_USER"),
		os.Getenv("RABBITMQ_PASSWORD"),
		os.Getenv("RABBITMQ_HOST"),
		os.Getenv("RABBITMQ_PORT"),
	)
}

func connectRabbit() (*amqp.Connection, *amqp.Channel, error) {
	var conn *amqp.Connection
	var err error
	// Broker có thể khởi động chậm hơn container này — retry 60s
	for i := 0; i < 30; i++ {
		conn, err = amqp.Dial(rabbitURL())
		if err == nil {
			break
		}
		log.Printf("RabbitMQ chưa sẵn sàng (%d/30): %v", i+1, err)
		time.Sleep(2 * time.Second)
	}
	if err != nil {
		return nil, nil, fmt.Errorf("connect RabbitMQ: %w", err)
	}

	ch, err := conn.Channel()
	if err != nil {
		conn.Close()
		return nil, nil, fmt.Errorf("open channel: %w", err)
	}

	// Đảm bảo topology tồn tại (idempotent — an toàn kể cả khi definitions.json chưa load)
	if err := declareTopology(ch); err != nil {
		return nil, nil, err
	}
	return conn, ch, nil
}

func declareTopology(ch *amqp.Channel) error {
	exchanges := []struct{ name, typ string }{
		{exchangeUser, "topic"},
		{exchangeFinance, "topic"},
	}
	for _, ex := range exchanges {
		if err := ch.ExchangeDeclare(ex.name, ex.typ, true, false, false, false, nil); err != nil {
			return fmt.Errorf("declare exchange %s: %w", ex.name, err)
		}
	}
	if _, err := ch.QueueDeclare(queueNotify, true, false, false, false, amqp.Table{
		"x-queue-type":             "quorum",
		"x-dead-letter-exchange":   "educonnect.dlx",
	}); err != nil {
		return fmt.Errorf("declare queue: %w", err)
	}
	// Bind: user_events + finance_events → notification_queue
	bindings := []struct{ ex, key string }{
		{exchangeUser, "user.#"},
		{exchangeFinance, "finance.#"},
	}
	for _, b := range bindings {
		if err := ch.QueueBind(queueNotify, b.key, b.ex, false, nil); err != nil {
			return fmt.Errorf("bind %s → %s (%s): %w", b.ex, queueNotify, b.key, err)
		}
	}
	return nil
}

// ─── Email Sender ─────────────────────────────────────────────

func sendEmail(p NotifyPayload) error {
	host := os.Getenv("MAIL_HOST")
	port := os.Getenv("MAIL_PORT")
	user := os.Getenv("MAIL_USERNAME")
	pass := os.Getenv("MAIL_PASSWORD")
	from := os.Getenv("MAIL_FROM")

	auth := smtp.PlainAuth("", user, pass, host)
	msg := []byte(fmt.Sprintf(
		"From: %s\r\nTo: %s\r\nSubject: %s\r\nMIME-version: 1.0;\r\nContent-Type: text/html; charset=\"UTF-8\";\r\n\r\n%s",
		from, p.To, p.Subject, p.Body,
	))
	addr := fmt.Sprintf("%s:%s", host, port)
	return smtp.SendMail(addr, auth, from, []string{p.To}, msg)
}

// ─── Consumer loop ────────────────────────────────────────────

func consume(ch *amqp.Channel) error {
	msgs, err := ch.Consume(queueNotify, "notify-worker", false, false, false, false, nil)
	if err != nil {
		return fmt.Errorf("consume %s: %w", queueNotify, err)
	}

	log.Printf("📬 Đang lắng nghe %s (user_events + finance_events)...", queueNotify)
	for msg := range msgs {
		var p NotifyPayload
		if err := json.Unmarshal(msg.Body, &p); err != nil {
			log.Printf("⚠️ payload lỗi (msg %s): %v", msg.MessageId, err)
			msg.Nack(false, false) // → DLX
			continue
		}

		// Correlation ID để trace (nếu publisher gửi header)
		corrID := ""
		if v, ok := msg.Headers["x-correlation-id"]; ok {
			corrID = fmt.Sprintf("%v", v)
		}
		log.Printf("[corr=%s] nhận %s → %s (%s)", corrID, msg.RoutingKey, p.To, p.Type)

		switch p.Type {
		case "email":
			if err := sendEmail(p); err != nil {
				log.Printf("❌ email thất bại → %s: %v", p.To, err)
				msg.Nack(false, false) // dead-letter để xử lý sau
			} else {
				log.Printf("✅ email đã gửi → %s", p.To)
				msg.Ack(false)
			}
		default:
			log.Printf("⚠️ loại notification không hỗ trợ: %s", p.Type)
			msg.Ack(false) // không phải lỗi broker — ack để không loop
		}
	}
	return fmt.Errorf("consumer channel đóng")
}

// ─── HTTP Health Server ───────────────────────────────────────

func setupHTTP(port string) {
	r := gin.Default()
	r.GET("/health", func(c *gin.Context) {
		c.JSON(http.StatusOK, gin.H{
			"service": "notify-service",
			"status":  "healthy",
			"mode":    "rabbitmq-consumer",
			"queue":   queueNotify,
		})
	})
	log.Printf("Notify HTTP health server on port %s", port)
	r.Run(fmt.Sprintf(":%s", port))
}

// ─── Main ─────────────────────────────────────────────────────

func main() {
	port := os.Getenv("PORT")
	if port == "" {
		port = "8081"
	}

	conn, ch, err := connectRabbit()
	if err != nil {
		log.Fatalf("💥 Không kết nối được RabbitMQ: %v", err)
	}
	defer conn.Close()
	defer ch.Close()

	go func() {
		for {
			if err := consume(ch); err != nil {
				log.Printf("Consumer dừng (%v) — reconnect sau 5s...", err)
			}
			time.Sleep(5 * time.Second)
			// Reconnect: đóng channel cũ, mở lại
			conn2, ch2, err2 := connectRabbit()
			if err2 != nil {
				log.Printf("Reconnect thất bại: %v", err2)
				continue
			}
			conn.Close()
			ch.Close()
			conn, ch = conn2, ch2
		}
	}()

	setupHTTP(port)
}
