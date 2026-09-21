package auth

// T6.1 — auth.events consumer (finance Go).
//
// Nhận user.disabled / user.password_changed từ exchange `auth.events`
// (publisher: Laravel outbox PublishAuthEventJob, routing key = event_type,
// header x-event-type + x-idempotency-key, message_id = idempotency key)
// và duy trì revocation set near-realtime để middleware JWT từ chối token
// của user vừa bị khóa/đổi pass trước khi access token hết hạn.
//
// user.role_changed: chỉ log (finance đọc roles từ claims của token mới;
// TTL access 5-10p giới hạn độ trễ) — không revoke để tránh bắt logout oan.

import (
	"encoding/json"
	"log"
	"os"
	"strings"
	"sync"
	"time"

	amqp "github.com/rabbitmq/amqp091-go"
)

const (
	authEventsExchange = "auth.events"
	financeAuthQueue   = "finance_auth_events"
	authQueueBinding   = "user.#"

	// Revoke TTL bao phủ access TTL 5-10p + biên an toàn.
	revokeTTL = 15 * time.Minute
)

// ─── Revocation store (in-memory, lazy expiry) ─────────────────────────────

var revokedUsers sync.Map // userID(string) -> revokedUntil(time.Time)

// RevokeUser đánh dấu user bị thu hồi đến hết ttl.
func RevokeUser(userID string, ttl time.Duration) {
	if userID == "" {
		return
	}
	revokedUsers.Store(userID, time.Now().Add(ttl))
}

// IsUserRevoked true khi user đang trong thời gian thu hồi.
func IsUserRevoked(userID string) bool {
	if userID == "" {
		return false
	}
	v, ok := revokedUsers.Load(userID)
	if !ok {
		return false
	}
	until, ok := v.(time.Time)
	if !ok || time.Now().After(until) {
		revokedUsers.Delete(userID)
		return false
	}
	return true
}

// ─── Envelope (khớp outbox payload phía Laravel) ───────────────────────────

type AuthEventEnvelope struct {
	Event         string          `json:"event"`
	CorrelationID string          `json:"correlation_id"`
	IdempotencyKey string         `json:"idempotency_key"`
	UserID        json.RawMessage `json:"user_id"`
	User          *AuthEventUser  `json:"user"`
}

type AuthEventUser struct {
	ID json.RawMessage `json:"id"`
}

// normalizeID chấp nhận user_id dạng string ("123") hoặc number (123)
// lẫn dạng lồng user:{id:...} — đều trả về chuỗi thống nhất.
func normalizeID(envelope *AuthEventEnvelope) string {
	raw := envelope.UserID
	if len(raw) == 0 && envelope.User != nil {
		raw = envelope.User.ID
	}
	if len(raw) == 0 {
		return ""
	}
	var s string
	if err := json.Unmarshal(raw, &s); err == nil {
		return strings.TrimSpace(s)
	}
	var n json.Number
	if err := json.Unmarshal(raw, &n); err == nil {
		return n.String()
	}
	return ""
}

func eventType(msg amqp.Delivery, envelope *AuthEventEnvelope) string {
	if h, ok := msg.Headers["x-event-type"]; ok {
		if s, ok := h.(string); ok && s != "" {
			return s
		}
	}
	return envelope.Event
}

// ─── Idempotency (dedupe redelivery theo message_id) ────────────────────────

var (
	seenMessages = map[string]time.Time{}
	seenMu       sync.Mutex
)

func alreadyProcessed(messageID string) bool {
	if messageID == "" {
		return false
	}
	seenMu.Lock()
	defer seenMu.Unlock()
	if ts, ok := seenMessages[messageID]; ok && time.Since(ts) < time.Hour {
		return true
	}
	// Sweep mục cũ để map không phình.
	for k, ts := range seenMessages {
		if time.Since(ts) >= time.Hour {
			delete(seenMessages, k)
		}
	}
	seenMessages[messageID] = time.Now()
	return false
}

// ─── Consumer (reconnect loop, manual ack — cùng style user-sync) ───────────

func StartAuthEventsConsumer() {
	url := os.Getenv("RABBITMQ_URL")
	if url == "" {
		url = "amqp://educonnect:educonnect_dev@rabbitmq:5672"
	}
	for {
		conn, err := amqp.Dial(url)
		if err != nil {
			log.Printf("auth.events: RabbitMQ chưa sẵn sàng (retry 5s): %v", err)
			time.Sleep(5 * time.Second)
			continue
		}
		ch, err := conn.Channel()
		if err != nil {
			log.Printf("auth.events: open channel: %v", err)
			conn.Close()
			time.Sleep(5 * time.Second)
			continue
		}
		if err := declareAuthEventsTopology(ch); err != nil {
			log.Printf("auth.events: declare topology: %v", err)
			conn.Close()
			time.Sleep(5 * time.Second)
			continue
		}
		msgs, err := ch.Consume(financeAuthQueue, "finance-auth-worker", false, false, false, false, nil)
		if err != nil {
			log.Printf("auth.events: consume %s: %v", financeAuthQueue, err)
			conn.Close()
			time.Sleep(5 * time.Second)
			continue
		}
		log.Printf("📬 Finance auth-events sẵn sàng trên %s (auth.events)", financeAuthQueue)
		for msg := range msgs {
			handleAuthEvent(ch, msg)
		}
		log.Println("auth.events: RabbitMQ connection closed — reconnect sau 5s")
		conn.Close()
		time.Sleep(5 * time.Second)
	}
}

// declareAuthEventsTopology khớp definitions.json (quorum + DLX educonnect.dlx).
func declareAuthEventsTopology(ch *amqp.Channel) error {
	if err := ch.ExchangeDeclare(authEventsExchange, "topic", true, false, false, false, nil); err != nil {
		return err
	}
	args := amqp.Table{
		"x-queue-type":           "quorum",
		"x-dead-letter-exchange": "educonnect.dlx",
	}
	if _, err := ch.QueueDeclare(financeAuthQueue, true, false, false, false, args); err != nil {
		return err
	}
	return ch.QueueBind(financeAuthQueue, authQueueBinding, authEventsExchange, false, nil)
}

func handleAuthEvent(ch *amqp.Channel, msg amqp.Delivery) {
	fail := func() { _ = msg.Nack(false, false) } // → DLX

	var envelope AuthEventEnvelope
	if err := json.Unmarshal(msg.Body, &envelope); err != nil {
		log.Printf("auth.events: body không phải JSON, drop → DLX: %v", err)
		fail()
		return
	}

	// Idempotency: redelivery (cùng message_id) thì ack bỏ qua.
	if alreadyProcessed(msg.MessageId) {
		_ = msg.Ack(false)
		return
	}

	etype := eventType(msg, &envelope)
	userID := normalizeID(&envelope)
	corr := envelope.CorrelationID
	if corr == "" {
		corr = "-"
	}

	switch etype {
	case "user.disabled", "user.password_changed":
		if userID == "" {
			log.Printf("[corr=%s] %s thiếu user_id, drop → DLX", corr, etype)
			fail()
			return
		}
		RevokeUser(userID, revokeTTL)
		log.Printf("[corr=%s] %s → revoke user=%s (%s)", corr, etype, userID, revokeTTL)
	case "user.role_changed":
		// Roles đọc từ claims token mới; không revoke (tránh logout oan).
		log.Printf("[corr=%s] user.role_changed user=%s (roles refresh theo token mới)", corr, userID)
	default:
		log.Printf("[corr=%s] event lạ %q, ack bỏ qua", corr, etype)
	}

	_ = msg.Ack(false)
}
