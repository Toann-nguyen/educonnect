package auth

import (
	"context"
	"encoding/json"
	"fmt"
	"log"
	"os"
	"time"

	amqp "github.com/rabbitmq/amqp091-go"
	"github.com/redis/go-redis/v9"
)

const (
	AuthExchange    = "auth.events"
	AuthQueueNotify = "notify_auth_events"
)

type AuthEvent struct {
	Event          string                 `json:"event"`
	Version        int                    `json:"version"`
	CorrelationID  string                 `json:"correlation_id"`
	IdempotencyKey string                 `json:"idempotency_key"`
	OccurredAt     time.Time              `json:"occurred_at"`
	AggregateID    interface{}            `json:"aggregate_id"`
	Payload        map[string]interface{} `json:"payload"`
}

func StartAuthConsumer(ctx context.Context, rdb *redis.Client) {
	InitCacheWithClient(rdb)
	url := fmt.Sprintf("amqp://%s:%s@%s:%s/",
		os.Getenv("RABBITMQ_USER"), os.Getenv("RABBITMQ_PASSWORD"),
		os.Getenv("RABBITMQ_HOST"), os.Getenv("RABBITMQ_PORT"))
	if os.Getenv("RABBITMQ_HOST") == "" {
		url = "amqp://educonnect:educonnect_dev@rabbitmq:5672/"
	}
	for {
		conn, err := amqp.Dial(url)
		if err != nil {
			log.Printf("[auth] RabbitMQ chưa sẵn sàng (retry 5s): %v", err)
			time.Sleep(5 * time.Second)
			continue
		}
		ch, err := conn.Channel()
		if err != nil {
			log.Printf("[auth] open channel: %v", err)
			conn.Close()
			time.Sleep(5 * time.Second)
			continue
		}
		if err := declareAuthTopology(ch); err != nil {
			log.Printf("[auth] declare topology: %v", err)
			conn.Close()
			time.Sleep(5 * time.Second)
			continue
		}
		msgs, err := ch.Consume(AuthQueueNotify, "notify-auth-worker", false, false, false, false, nil)
		if err != nil {
			log.Printf("[auth] consume %s: %v", AuthQueueNotify, err)
			conn.Close()
			time.Sleep(5 * time.Second)
			continue
		}
		log.Printf("📬 Notify auth-events sẵn sàng trên %s (auth.events)", AuthQueueNotify)
		for msg := range msgs {
			handleAuthMessage(ctx, ch, msg, rdb)
		}
		log.Println("[auth] RabbitMQ channel closed — reconnect sau 5s")
		conn.Close()
		time.Sleep(5 * time.Second)
	}
}

func declareAuthTopology(ch *amqp.Channel) error {
	if err := ch.ExchangeDeclare(AuthExchange, "topic", true, false, false, false, nil); err != nil {
		return err
	}
	if _, err := ch.QueueDeclare(AuthQueueNotify, true, false, false, false, amqp.Table{
		"x-queue-type":           "quorum",
		"x-dead-letter-exchange": "educonnect.dlx",
	}); err != nil {
		return err
	}
	if _, err := ch.QueueDeclare(AuthQueueNotify+".dlq", true, false, false, false, amqp.Table{"x-queue-type": "quorum"}); err != nil {
		return err
	}
	if err := ch.QueueBind(AuthQueueNotify+".dlq", "", "educonnect.dlx", false, nil); err != nil {
		return err
	}
	return ch.QueueBind(AuthQueueNotify, "auth.#", AuthExchange, false, nil)
}

func handleAuthMessage(ctx context.Context, ch *amqp.Channel, msg amqp.Delivery, rdb *redis.Client) {
	var ev AuthEvent
	if err := json.Unmarshal(msg.Body, &ev); err != nil {
		log.Printf("[auth] ⚠️ payload lỗi: %v body=%s", err, string(msg.Body[:mini(200, len(msg.Body))]))
		ch.Nack(msg.DeliveryTag, false, false)
		return
	}
	corrID := ev.CorrelationID
	if corrID == "" {
		if v, ok := msg.Headers["x-correlation-id"]; ok {
			corrID = fmt.Sprintf("%v", v)
		}
	}
	idemKey := ev.IdempotencyKey
	if idemKey == "" {
		idemKey = msg.MessageId
		if idemKey == "" {
			if v, ok := msg.Headers["x-idempotency-key"]; ok {
				idemKey = fmt.Sprintf("%v", v)
			}
		}
	}
	if idemKey == "" {
		idemKey = ev.CorrelationID
	}
	if rdb != nil && idemKey != "" {
		redisKey := "auth:idempotency:" + idemKey
		ok, err := rdb.SetNX(ctx, redisKey, "1", 24*time.Hour).Result()
		if err == nil && !ok {
			log.Printf("[corr=%s] auth duplicate idem=%s event=%s → ack", corrID, idemKey, ev.Event)
			ch.Ack(msg.DeliveryTag, false)
			return
		}
	}
	var userID uint
	if ev.Payload != nil {
		if v, ok := ev.Payload["user_id"]; ok {
			switch x := v.(type) {
			case float64:
				userID = uint(x)
			case int:
				userID = uint(x)
			case int64:
				userID = uint(x)
			case string:
				fmt.Sscanf(x, "%d", &userID)
			}
		}
	}
	if userID != 0 {
		if err := InvalidatePermissionCache(ctx, userID); err != nil {
			log.Printf("[corr=%s] cache invalidate failed user=%d: %v → DLQ", corrID, userID, err)
			ch.Nack(msg.DeliveryTag, false, false)
			return
		}
		log.Printf("[corr=%s] auth invalidate user:%d:permissions event=%s idem=%s", corrID, userID, ev.Event, idemKey)
	} else {
		if ev.Event == "auth.role_updated" || ev.Event == "auth.permission_updated" {
			_ = InvalidateAll(ctx)
			log.Printf("[corr=%s] auth clearAll event=%s idem=%s", corrID, ev.Event, idemKey)
		} else {
			log.Printf("[corr=%s] auth event no user_id event=%s → ack", corrID, ev.Event)
		}
	}
	ch.Ack(msg.DeliveryTag, false)
}

func mini(a, b int) int { if a < b { return a }; return b }
