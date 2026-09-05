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

func InitCacheWithClient(rdb *redis.Client) {
	redisCli = rdb
	if rdb != nil {
		log.Println("auth cache Redis injected")
		return
	}
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
	})
	log.Println("auth cache Redis:", host+":"+port)
}

func InvalidatePermissionCache(ctx context.Context, userID uint) error {
	if redisCli == nil {
		InitCacheWithClient(nil)
	}
	key := fmt.Sprintf("user:%d:permissions", userID)
	if err := redisCli.Del(ctx, key).Err(); err != nil {
		return err
	}
	log.Printf("[auth-cache] invalidated %s", key)
	return nil
}

func InvalidateAll(ctx context.Context) error {
	if redisCli == nil {
		InitCacheWithClient(nil)
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
