package auth

import (
	"context"
	"fmt"
	"log"
	"os"
	"time"

	"github.com/redis/go-redis/v9"
)

var redisCli *redis.Client

// InitCache — khởi tạo Redis client cho permission cache invalidation (T6.1)
func InitCache() {
	host := os.Getenv("REDIS_HOST")
	if host == "" {
		host = "redis"
	}
	port := os.Getenv("REDIS_PORT")
	if port == "" {
		port = "6379"
	}
	redisCli = redis.NewClient(&redis.Options{
		Addr:     fmt.Sprintf("%s:%s", host, port),
		Password: os.Getenv("REDIS_PASSWORD"),
		DB:       0,
	})
	log.Println("auth cache Redis:", host+":"+port)
}

// InvalidatePermissionCache — xóa key user:{id}:permissions (TTL 300)
// Được gọi bởi auth.events consumer khi nhận auth.* event
func InvalidatePermissionCache(ctx context.Context, userID uint) error {
	if redisCli == nil {
		InitCache()
	}
	key := fmt.Sprintf("user:%d:permissions", userID)
	if err := redisCli.Del(ctx, key).Err(); err != nil {
		return err
	}
	log.Printf("[auth-cache] invalidated %s", key)
	return nil
}

// InvalidateAll — clear all permission cache keys (khi role định nghĩa thay đổi)
func InvalidateAll(ctx context.Context) error {
	if redisCli == nil {
		InitCache()
	}
	iter := redisCli.Scan(ctx, 0, "user:*:permissions", 100).Iterator()
	count := 0
	for iter.Next(ctx) {
		redisCli.Del(ctx, iter.Val())
		count++
		time.Sleep(5 * time.Millisecond)
	}
	log.Printf("[auth-cache] invalidateAll scanned %d keys", count)
	return iter.Err()
}
