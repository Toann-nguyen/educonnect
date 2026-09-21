package grpcserver

import (
	"context"
	"fmt"
	"time"

	"educonnect/finance/internal/pkg/proto/finance"
	"google.golang.org/grpc"
	"google.golang.org/grpc/codes"
	"google.golang.org/grpc/status"
)

// InvoiceServiceServer implements the InvoiceService gRPC service
type InvoiceServiceServer struct {
	finance.UnimplementedInvoiceServiceServer
	// Dependencies will be injected here
	// invoiceRepo repositories.InvoiceRepository
	// feeTypeRepo repositories.FeeTypeRepository
	// userClient  auth.UserServiceClient
}

// NewInvoiceServiceServer creates a new InvoiceServiceServer
func NewInvoiceServiceServer() *InvoiceServiceServer {
	return &InvoiceServiceServer{}
}

// GetInvoice retrieves a single invoice by ID
func (s *InvoiceServiceServer) GetInvoice(ctx context.Context, req *finance.GetInvoiceRequest) (*finance.GetInvoiceResponse, error) {
	if req.Id == "" {
		return nil, status.Error(codes.InvalidArgument, "invoice ID is required")
	}

	// TODO: Implement business logic
	// invoice, err := s.invoiceRepo.GetByID(ctx, req.Id)
	// if err != nil {
	// 	return nil, status.Error(codes.NotFound, fmt.Sprintf("invoice not found: %v", err))
	// }

	// Convert domain model to proto message
	// protoInvoice := convertToProtoInvoice(invoice)

	return &finance.GetInvoiceResponse{
		// Invoice: protoInvoice,
	}, status.Error(codes.Unimplemented, "GetInvoice is not yet implemented")
}

// GetInvoicesByStudent retrieves invoices for a specific student
func (s *InvoiceServiceServer) GetInvoicesByStudent(ctx context.Context, req *finance.GetInvoicesByStudentRequest) (*finance.GetInvoicesResponse, error) {
	if req.StudentId == "" {
		return nil, status.Error(codes.InvalidArgument, "student ID is required")
	}

	// TODO: Implement pagination and filtering
	// invoices, total, err := s.invoiceRepo.GetByStudentID(ctx, req.StudentId, req.Pagination)

	return &finance.GetInvoicesResponse{
		// Invoices:   protoInvoices,
		// Pagination: convertToProtoPagination(total, req.Pagination),
	}, status.Error(codes.Unimplemented, "GetInvoicesByStudent is not yet implemented")
}

// GetInvoicesByUser retrieves invoices for a specific user
func (s *InvoiceServiceServer) GetInvoicesByUser(ctx context.Context, req *finance.GetInvoicesByUserRequest) (*finance.GetInvoicesResponse, error) {
	if req.UserId == "" {
		return nil, status.Error(codes.InvalidArgument, "user ID is required")
	}

	// TODO: Implement pagination and filtering
	// invoices, total, err := s.invoiceRepo.GetByUserID(ctx, req.UserId, req.Pagination)

	return &finance.GetInvoicesResponse{}, status.Error(codes.Unimplemented, "GetInvoicesByUser is not yet implemented")
}

// CreateInvoice creates a new invoice
func (s *InvoiceServiceServer) CreateInvoice(ctx context.Context, req *finance.CreateInvoiceRequest) (*finance.CreateInvoiceResponse, error) {
	if req.StudentId == "" {
		return nil, status.Error(codes.InvalidArgument, "student ID is required")
	}

	if len(req.Items) == 0 {
		return nil, status.Error(codes.InvalidArgument, "at least one invoice item is required")
	}

	// TODO: Implement business logic
	// - Validate fee types
	// - Calculate total amount
	// - Create invoice with items
	// - Publish event to RabbitMQ

	return &finance.CreateInvoiceResponse{}, status.Error(codes.Unimplemented, "CreateInvoice is not yet implemented")
}

// UpdateInvoiceStatus updates the status of an invoice
func (s *InvoiceServiceServer) UpdateInvoiceStatus(ctx context.Context, req *finance.UpdateInvoiceStatusRequest) (*finance.UpdateInvoiceStatusResponse, error) {
	if req.Id == "" {
		return nil, status.Error(codes.InvalidArgument, "invoice ID is required")
	}

	if req.Status == finance.InvoiceStatus_INVOICE_STATUS_UNSPECIFIED {
		return nil, status.Error(codes.InvalidArgument, "valid status is required")
	}

	// TODO: Implement business logic
	// - Validate status transition
	// - Update invoice status
	// - If status is PAID, trigger notification

	return &finance.UpdateInvoiceStatusResponse{}, status.Error(codes.Unimplemented, "UpdateInvoiceStatus is not yet implemented")
}

// GetFeeTypes retrieves all fee types
func (s *InvoiceServiceServer) GetFeeTypes(ctx context.Context, req *finance.GetFeeTypesRequest) (*finance.GetFeeTypesResponse, error) {
	// TODO: Implement filtering and pagination
	// feeTypes, total, err := s.feeTypeRepo.GetAll(ctx, req.ActiveOnly, req.Pagination)

	return &finance.GetFeeTypesResponse{}, status.Error(codes.Unimplemented, "GetFeeTypes is not yet implemented")
}

// Helper functions (to be implemented)
func convertToProtoInvoice(invoice interface{}) *finance.Invoice {
	// Convert domain model to proto message
	return &finance.Invoice{}
}

func convertToProtoPagination(total int64, req *finance.PaginationRequest) *finance.PaginationResponse {
	pageSize := int32(10)
	if req != nil && req.PageSize > 0 {
		pageSize = req.PageSize
	}

	totalPages := int32((total + int64(pageSize) - 1) / int64(pageSize))
	currentPage := int32(1)
	if req != nil && req.Page > 0 {
		currentPage = req.Page
	}

	return &finance.PaginationResponse{
		TotalItems:  int32(total),
		TotalPages:  totalPages,
		CurrentPage: currentPage,
		PageSize:    pageSize,
		HasNext:     currentPage < totalPages,
		HasPrev:     currentPage > 1,
	}
}

// PaymentServiceServer implements the PaymentService gRPC service
type PaymentServiceServer struct {
	finance.UnimplementedPaymentServiceServer
	// paymentRepo repositories.PaymentRepository
	// invoiceRepo repositories.InvoiceRepository
}

// NewPaymentServiceServer creates a new PaymentServiceServer
func NewPaymentServiceServer() *PaymentServiceServer {
	return &PaymentServiceServer{}
}

// CreatePayment creates a new payment
func (s *PaymentServiceServer) CreatePayment(ctx context.Context, req *finance.CreatePaymentRequest) (*finance.CreatePaymentResponse, error) {
	if req.InvoiceId == "" {
		return nil, status.Error(codes.InvalidArgument, "invoice ID is required")
	}

	if req.Amount == nil || req.Amount.Amount <= 0 {
		return nil, status.Error(codes.InvalidArgument, "valid payment amount is required")
	}

	// TODO: Implement business logic
	// - Validate invoice exists and is not fully paid
	// - Create payment record
	// - Update invoice paid amount
	// - Publish payment event to RabbitMQ for Notify service

	return &finance.CreatePaymentResponse{}, status.Error(codes.Unimplemented, "CreatePayment is not yet implemented")
}

// GetPaymentsByInvoice retrieves all payments for an invoice
func (s *PaymentServiceServer) GetPaymentsByInvoice(ctx context.Context, req *finance.GetPaymentsByInvoiceRequest) (*finance.GetPaymentsResponse, error) {
	if req.InvoiceId == "" {
		return nil, status.Error(codes.InvalidArgument, "invoice ID is required")
	}

	// TODO: Implement retrieval logic
	// payments, err := s.paymentRepo.GetByInvoiceID(ctx, req.InvoiceId)

	return &finance.GetPaymentsResponse{}, status.Error(codes.Unimplemented, "GetPaymentsByInvoice is not yet implemented")
}

// FinanceUserServiceServer implements cross-service user lookup
type FinanceUserServiceServer struct {
	finance.UnimplementedFinanceUserServiceServer
	userClient interface{} // auth.UserServiceClient
}

// NewFinanceUserServiceServer creates a new FinanceUserServiceServer
func NewFinanceUserServiceServer() *FinanceUserServiceServer {
	return &FinanceUserServiceServer{}
}

// GetUser retrieves user information for finance operations
func (s *FinanceUserServiceServer) GetUser(ctx context.Context, req *finance.UUID) (*finance.UserRef, error) {
	if req.Value == "" {
		return nil, status.Error(codes.InvalidArgument, "user ID is required")
	}

	// TODO: Call Auth service via gRPC to get user info
	// userResp, err := s.userClient.GetUser(ctx, &auth.GetUserRequest{Id: req.Value})

	return &finance.UserRef{}, status.Error(codes.Unimplemented, "GetUser is not yet implemented")
}

// RegisterServices registers all finance gRPC services with the server
func RegisterServices(grpcServer *grpc.Server) {
	invoiceService := NewInvoiceServiceServer()
	paymentService := NewPaymentServiceServer()
	userService := NewFinanceUserServiceServer()

	finance.RegisterInvoiceServiceServer(grpcServer, invoiceService)
	finance.RegisterPaymentServiceServer(grpcServer, paymentService)
	finance.RegisterFinanceUserServiceServer(grpcServer, userService)
}

// Health check for gRPC server
func (s *InvoiceServiceServer) Check(ctx context.Context, req *finance.Empty) (*finance.HealthCheckResponse, error) {
	return &finance.HealthCheckResponse{
		Status: finance.HealthCheckResponse_SERVING,
	}, nil
}
