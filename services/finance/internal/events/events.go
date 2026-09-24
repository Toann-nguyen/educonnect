package events

import (
	"context"
	"crypto/rand"
	"encoding/hex"
	"encoding/json"
	"time"

	amqp "github.com/rabbitmq/amqp091-go"
	"google.golang.org/grpc/metadata"
)

const Exchange = "finance_events"

type Publisher interface {
	Publish(ctx context.Context, routingKey string, event map[string]any) error
}

type AMQPPublisher struct {
	url string
}

func NewAMQPPublisher(url string) *AMQPPublisher {
	if url == "" {
		url = "amqp://educonnect:educonnect_dev@rabbitmq:5672/"
	}
	return &AMQPPublisher{url: url}
}

func correlationID(ctx context.Context) string {
	if md, ok := metadata.FromIncomingContext(ctx); ok {
		if values := md.Get("x-correlation-id"); len(values) > 0 && values[0] != "" {
			return values[0]
		}
	}
	var id [16]byte
	if _, err := rand.Read(id[:]); err == nil {
		return hex.EncodeToString(id[:])
	}
	return time.Now().UTC().Format("20060102150405.000000000")
}

func (p *AMQPPublisher) Publish(ctx context.Context, routingKey string, event map[string]any) error {
	payload := make(map[string]any, len(event)+3)
	for key, value := range event {
		payload[key] = value
	}
	payload["correlation_id"] = correlationID(ctx)
	payload["occurred_at"] = time.Now().UTC()
	body, err := json.Marshal(payload)
	if err != nil {
		return err
	}

	conn, err := amqp.Dial(p.url)
	if err != nil {
		return err
	}
	defer conn.Close()
	channel, err := conn.Channel()
	if err != nil {
		return err
	}
	defer channel.Close()
	if err := channel.ExchangeDeclare(Exchange, "topic", true, false, false, false, nil); err != nil {
		return err
	}
	return channel.PublishWithContext(ctx, Exchange, routingKey, false, false, amqp.Publishing{
		ContentType:  "application/json",
		DeliveryMode: amqp.Persistent,
		Timestamp:    time.Now(),
		Body:         body,
	})
}
