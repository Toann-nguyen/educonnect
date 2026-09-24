package main

import (
	"context"
	"encoding/json"
	"errors"
	"net"
	"net/http"
	"os"
	"os/signal"
	"syscall"
	"time"

	"educonnect/internal/pkg/proto/school"
	"go.uber.org/zap"
	"google.golang.org/grpc"
	"google.golang.org/grpc/health"
	healthpb "google.golang.org/grpc/health/grpc_health_v1"
	"google.golang.org/grpc/reflection"
)

type server struct {
	school.UnimplementedStudentServiceServer
	school.UnimplementedClassServiceServer
	school.UnimplementedGradeServiceServer
	school.UnimplementedScheduleServiceServer
}

func main() {
	logger, err := zap.NewProduction()
	if err != nil {
		panic(err)
	}
	defer logger.Sync()

	grpcPort := env("GRPC_PORT", "50053")
	healthPort := env("HEALTH_PORT", "8080")

	listener, err := net.Listen("tcp", ":"+grpcPort)
	if err != nil {
		logger.Fatal("listen", zap.Error(err))
	}

	grpcServer := grpc.NewServer()
	school.RegisterStudentServiceServer(grpcServer, &server{})
	school.RegisterClassServiceServer(grpcServer, &server{})
	school.RegisterGradeServiceServer(grpcServer, &server{})
	school.RegisterScheduleServiceServer(grpcServer, &server{})
	healthServer := health.NewServer()
	healthServer.SetServingStatus("", healthpb.HealthCheckResponse_SERVING)
	healthpb.RegisterHealthServer(grpcServer, healthServer)
	reflection.Register(grpcServer)

	mux := http.NewServeMux()
	mux.HandleFunc("/health", func(w http.ResponseWriter, _ *http.Request) {
		w.Header().Set("Content-Type", "application/json")
		_ = json.NewEncoder(w).Encode(map[string]string{"service": "school-grpc", "status": "healthy"})
	})
	httpServer := &http.Server{Addr: ":" + healthPort, Handler: mux}

	go func() {
		logger.Info("HTTP health server starting", zap.String("address", httpServer.Addr))
		if err := httpServer.ListenAndServe(); err != nil && !errors.Is(err, http.ErrServerClosed) {
			logger.Fatal("health server", zap.Error(err))
		}
	}()

	serveErr := make(chan error, 1)
	go func() {
		logger.Info("gRPC server starting", zap.String("address", listener.Addr().String()))
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

func env(key, fallback string) string {
	if value := os.Getenv(key); value != "" {
		return value
	}
	return fallback
}
