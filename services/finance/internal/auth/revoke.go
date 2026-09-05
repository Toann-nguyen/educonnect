package auth

import (
	"context"
	"fmt"
	"log"
	"os"
	"strconv"
	"sync"

	"github.com/redis/go-redis/v9"
)

const revokePrefix = "educonnect:auth"

var (
	rdbRev  *redis.Client
	revOnce sync.Once
)

// InitRevoke — khởi tạo Redis client cho revoke check (idempotent).
func InitRevoke() {
	revOnce.Do(func() {
		host := os.Getenv("REDIS_HOST")
		if host == "" {
			host = "redis"
		}
		port := os.Getenv("REDIS_PORT")
		if port == "" {
			port = "6379"
		}
		addr := fmt.Sprintf("%s:%s", host, port)
		rdbRev = redis.NewClient(&redis.Options{
			Addr:     addr,
			Password: os.Getenv("REDIS_PASSWORD"),
		})
		ctx := context.Background()
		if err := rdbRev.Ping(ctx).Err(); err != nil {
			log.Printf("Revoke Redis ping failed %s: %v (revoke check sẽ fail-open)", addr, err)
		} else {
			log.Printf("Revoke Redis connected %s", addr)
		}
	})
}

func revokeRdb() *redis.Client {
	if rdbRev == nil {
		InitRevoke()
	}
	return rdbRev
}

// IsJTIBlacklisted — jti nằm trong blacklist (logout 1 session) → true là đã thu hồi.
func IsJTIBlacklisted(jti string) bool {
	if jti == "" {
		return false
	}
	ctx := context.Background()
	c := revokeRdb()
	if c == nil {
		return false
	}
	exists, err := c.Exists(ctx, revokePrefix+":blacklist:"+jti).Result()
	if err != nil {
		return false
	}
	return exists == 1
}

// IsSidRevoked — sid bị revoke (logout 1 session / revoke session) → true là đã thu hồi.
func IsSidRevoked(sid string) bool {
	if sid == "" {
		return false
	}
	ctx := context.Background()
	c := revokeRdb()
	if c == nil {
		return false
	}
	exists, err := c.Exists(ctx, revokePrefix+":sid_revoked:"+sid).Result()
	if err != nil {
		return false
	}
	return exists == 1
}

// IsTVStale — so claim tv với Redis user:{id}:tv, nếu lệch → stale (đổi pass/khóa/logout all).
func IsTVStale(userID string, claimTV float64) bool {
	if userID == "" {
		return false
	}
	ctx := context.Background()
	c := revokeRdb()
	if c == nil {
		return false
	}
	key := revokePrefix + ":user:" + userID + ":tv"
	val, err := c.Get(ctx, key).Result()
	if err != nil {
		return false // chưa cache → không chặn (DB là source ở PHP)
	}
	stored, err := strconv.Atoi(val)
	if err != nil {
		return false
	}
	return int(claimTV) != stored
}

// IsTVStaleInt — overload cho int.
func IsTVStaleInt(userID string, claimTV int) bool {
	return IsTVStale(userID, float64(claimTV))
}

var _ = IsTVStaleInt
