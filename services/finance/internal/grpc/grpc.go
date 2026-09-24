package grpcserver

import (
	"context"
	"fmt"
	"net"

	"go.uber.org/zap"
	"google.golang.org/grpc"
	"google.golang.org/grpc/reflection"
)

// Config holds gRPC server configuration
type Config struct {
	Port           int
	MaxRecvMsgSize int
	MaxSendMsgSize int
}

// Server wraps the gRPC server with dependencies
type Server struct {
	server *grpc.Server
	config Config
	logger *zap.Logger
}

// NewServer creates a new gRPC server
func NewServer(config Config, logger *zap.Logger) *Server {
	// Create gRPC server with options
	grpcOptions := []grpc.ServerOption{
		grpc.MaxRecvMsgSize(config.MaxRecvMsgSize),
		grpc.MaxSendMsgSize(config.MaxSendMsgSize),
		grpc.UnaryInterceptor(unaryLoggerInterceptor(logger)),
		grpc.StreamInterceptor(streamLoggerInterceptor(logger)),
	}

	grpcServer := grpc.NewServer(grpcOptions...)

	return &Server{
		server: grpcServer,
		config: config,
		logger: logger,
	}
}

// RegisterServices registers all gRPC services
func (s *Server) RegisterServices() {
	// Register finance services
	RegisterServices(s.server)

	// Enable reflection for tools like grpcurl
	reflection.Register(s.server)

	s.logger.Info("gRPC services registered successfully")
}

// Start starts the gRPC server
func (s *Server) Start() error {
	addr := fmt.Sprintf(":%d", s.config.Port)
	lis, err := net.Listen("tcp", addr)
	if err != nil {
		return fmt.Errorf("failed to listen on %s: %w", addr, err)
	}

	s.logger.Info("gRPC server starting",
		zap.String("address", addr),
		zap.Int("port", s.config.Port))

	if err := s.server.Serve(lis); err != nil {
		return fmt.Errorf("failed to serve gRPC: %w", err)
	}

	return nil
}

// Stop gracefully stops the gRPC server
func (s *Server) Stop() {
	s.logger.Info("stopping gRPC server")
	s.server.GracefulStop()
}

// ForceStop forcefully stops the gRPC server
func (s *Server) ForceStop() {
	s.logger.Warn("force stopping gRPC server")
	s.server.Stop()
}

// GetServer returns the underlying gRPC server
func (s *Server) GetServer() *grpc.Server {
	return s.server
}

// unaryLoggerInterceptor logs unary RPC calls
func unaryLoggerInterceptor(logger *zap.Logger) grpc.UnaryServerInterceptor {
	return func(ctx context.Context, req interface{}, info *grpc.UnaryServerInfo, handler grpc.UnaryHandler) (interface{}, error) {
		logger.Debug("gRPC unary request",
			zap.String("method", info.FullMethod))

		resp, err := handler(ctx, req)

		if err != nil {
			logger.Error("gRPC unary request failed",
				zap.String("method", info.FullMethod),
				zap.Error(err))
		}

		return resp, err
	}
}

// streamLoggerInterceptor logs streaming RPC calls
func streamLoggerInterceptor(logger *zap.Logger) grpc.StreamServerInterceptor {
	return func(srv interface{}, ss grpc.ServerStream, info *grpc.StreamServerInfo, handler grpc.StreamHandler) error {
		logger.Debug("gRPC stream request",
			zap.String("method", info.FullMethod))

		err := handler(srv, ss)

		if err != nil {
			logger.Error("gRPC stream request failed",
				zap.String("method", info.FullMethod),
				zap.Error(err))
		}

		return err
	}
}
