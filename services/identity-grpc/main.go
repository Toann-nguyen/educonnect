package main

import (
	"context"
	"encoding/json"
	"errors"
	"fmt"
	"log"
	"net"
	"net/http"
	"os"
	"os/signal"
	"syscall"
	"time"

	"educonnect/internal/pkg/proto/auth"
	"go.uber.org/zap"
	"google.golang.org/grpc"
	"google.golang.org/grpc/health"
	healthpb "google.golang.org/grpc/health/grpc_health_v1"
	"google.golang.org/grpc/reflection"
	"gorm.io/driver/mysql"
	"gorm.io/gorm"

	"educonnect/identity-grpc/internal/events"
	"educonnect/identity-grpc/internal/phpclient"
	"educonnect/identity-grpc/internal/service"
)

func env(key, fallback string) string {
	if value := os.Getenv(key); value != "" {
		return value
	}
	return fallback
}

func openDB() *gorm.DB {
	dsn := fmt.Sprintf("%s:%s@tcp(%s:%s)/%s?charset=utf8mb4&parseTime=True&loc=Local",
		env("DB_IDENTITY_USERNAME", "identity_user"),
		os.Getenv("DB_IDENTITY_PASSWORD"),
		env("DB_IDENTITY_HOST", "mysql-identity"),
		env("DB_IDENTITY_PORT", "3306"),
		env("DB_IDENTITY_DATABASE", "identity_db"),
	)
	db, err := gorm.Open(mysql.Open(dsn), &gorm.Config{})
	if err != nil {
		log.Fatalf("Failed to connect to identity_db: %v", err)
	}
	return db
}

func main() {
	logger, err := zap.NewProduction()
	if err != nil {
		panic(err)
	}
	defer logger.Sync()

	db := openDB()
	svc := service.New(db, phpclient.New(), events.NewAMQPPublisher(os.Getenv("RABBITMQ_URL")))
	grpcServer := grpc.NewServer()
	auth.RegisterAuthServiceServer(grpcServer, svc)
	auth.RegisterUserServiceServer(grpcServer, svc)
	healthServer := health.NewServer()
	healthServer.SetServingStatus("", healthpb.HealthCheckResponse_SERVING)
	healthpb.RegisterHealthServer(grpcServer, healthServer)
	reflection.Register(grpcServer)

	listener, err := net.Listen("tcp", ":"+env("GRPC_PORT", "50051"))
	if err != nil {
		logger.Fatal("listen", zap.Error(err))
	}
	mux := http.NewServeMux()
	mux.HandleFunc("/health", func(w http.ResponseWriter, _ *http.Request) {
		w.Header().Set("Content-Type", "application/json")
		_ = json.NewEncoder(w).Encode(map[string]string{"service": "identity-grpc", "status": "healthy"})
	})
	httpServer := &http.Server{Addr: ":" + env("HEALTH_PORT", "8080"), Handler: mux}
	go func() {
		if err := httpServer.ListenAndServe(); err != nil && !errors.Is(err, http.ErrServerClosed) {
			logger.Fatal("health server", zap.Error(err))
		}
	}()
	serveErr := make(chan error, 1)
	go func() {
		serveErr <- grpcServer.Serve(listener)
	}()
	signals := make(chan os.Signal, 1)
	signal.Notify(signals, syscall.SIGINT, syscall.SIGTERM)
	select {
	case err := <-serveErr:
		logger.Fatal("gRPC server", zap.Error(err))
	case sig := <-signals:
		logger.Info("shutting down", zap.String("signal", sig.String()))
	}
	healthServer.SetServingStatus("", healthpb.HealthCheckResponse_NOT_SERVING)
	grpcServer.GracefulStop()
	ctx, cancel := context.WithTimeout(context.Background(), 5*time.Second)
	defer cancel()
	if err := httpServer.Shutdown(ctx); err != nil {
		logger.Error("health server shutdown", zap.Error(err))
	}
}
