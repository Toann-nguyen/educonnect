package grpcserver

import (
	"context"
	"encoding/json"
	"errors"
	"log"
	"math"
	"strconv"
	"strings"
	"time"

	"google.golang.org/grpc"
	"google.golang.org/grpc/codes"
	"google.golang.org/grpc/status"
	"google.golang.org/protobuf/types/known/timestamppb"
	"gorm.io/gorm"

	"educonnect/finance/internal/auth"
	"educonnect/finance/internal/events"
	"educonnect/finance/internal/model"
	"educonnect/finance/internal/pkg/proto/common"
	"educonnect/finance/internal/pkg/proto/finance"
)

const defaultCurrency = "VND"

type InvoiceServiceServer struct {
	finance.UnimplementedInvoiceServiceServer
	db        *gorm.DB
	publisher events.Publisher
}

func NewInvoiceServiceServer(db *gorm.DB, publisher events.Publisher) *InvoiceServiceServer {
	return &InvoiceServiceServer{db: db, publisher: publisher}
}

func parseID(value, field string) (uint, error) {
	id, err := strconv.ParseUint(strings.TrimSpace(value), 10, 32)
	if err != nil || id == 0 {
		return 0, status.Errorf(codes.InvalidArgument, "invalid %s", field)
	}
	return uint(id), nil
}

func moneyFromFloat(amount float64, currency string) *common.Money {
	if currency == "" {
		currency = defaultCurrency
	}
	return &common.Money{Amount: int64(math.Round(amount)), Currency: currency}
}

func moneyToFloat(money *common.Money) (float64, string, error) {
	if money == nil {
		return 0, "", status.Error(codes.InvalidArgument, "amount is required")
	}
	currency := money.Currency
	if currency == "" {
		currency = defaultCurrency
	}
	if money.Amount < 0 {
		return 0, "", status.Error(codes.InvalidArgument, "amount cannot be negative")
	}
	return float64(money.Amount), currency, nil
}

func timestamp(value time.Time) *timestamppb.Timestamp {
	if value.IsZero() {
		return nil
	}
	return timestamppb.New(value)
}

func optionalTimestamp(value *time.Time) *timestamppb.Timestamp {
	if value == nil {
		return nil
	}
	return timestamp(*value)
}

func invoiceStatusToProto(statusValue string) finance.InvoiceStatus {
	switch strings.ToLower(statusValue) {
	case "draft":
		return finance.InvoiceStatus_INVOICE_STATUS_DRAFT
	case "paid":
		return finance.InvoiceStatus_INVOICE_STATUS_PAID
	case "cancelled", "canceled":
		return finance.InvoiceStatus_INVOICE_STATUS_CANCELLED
	case "overdue":
		return finance.InvoiceStatus_INVOICE_STATUS_OVERDUE
	default:
		return finance.InvoiceStatus_INVOICE_STATUS_PENDING
	}
}

func invoiceStatusToModel(statusValue finance.InvoiceStatus) (string, error) {
	switch statusValue {
	case finance.InvoiceStatus_INVOICE_STATUS_DRAFT:
		return "draft", nil
	case finance.InvoiceStatus_INVOICE_STATUS_PENDING:
		return "pending", nil
	case finance.InvoiceStatus_INVOICE_STATUS_PAID:
		return "paid", nil
	case finance.InvoiceStatus_INVOICE_STATUS_CANCELLED:
		return "cancelled", nil
	case finance.InvoiceStatus_INVOICE_STATUS_OVERDUE:
		return "overdue", nil
	default:
		return "", status.Error(codes.InvalidArgument, "valid status is required")
	}
}

func paymentMethodToModel(method finance.PaymentMethod) (string, error) {
	switch method {
	case finance.PaymentMethod_PAYMENT_METHOD_CASH:
		return "cash", nil
	case finance.PaymentMethod_PAYMENT_METHOD_BANK_TRANSFER:
		return "bank_transfer", nil
	case finance.PaymentMethod_PAYMENT_METHOD_CREDIT_CARD:
		return "credit_card", nil
	case finance.PaymentMethod_PAYMENT_METHOD_E_WALLET:
		return "e_wallet", nil
	default:
		return "", status.Error(codes.InvalidArgument, "valid payment method is required")
	}
}

func paymentMethodToProto(method string) finance.PaymentMethod {
	switch strings.ToLower(method) {
	case "bank_transfer", "banking":
		return finance.PaymentMethod_PAYMENT_METHOD_BANK_TRANSFER
	case "credit_card":
		return finance.PaymentMethod_PAYMENT_METHOD_CREDIT_CARD
	case "e_wallet", "vnpay":
		return finance.PaymentMethod_PAYMENT_METHOD_E_WALLET
	default:
		return finance.PaymentMethod_PAYMENT_METHOD_CASH
	}
}

func paidAmount(db *gorm.DB, invoiceID uint) (float64, error) {
	var total *float64
	if err := db.Model(&model.Payment{}).Where("invoice_id = ?", invoiceID).Select("COALESCE(SUM(amount), 0)").Scan(&total).Error; err != nil {
		return 0, err
	}
	if total == nil {
		return 0, nil
	}
	return *total, nil
}

func (s *InvoiceServiceServer) loadInvoice(id uint) (model.Invoice, []model.InvoiceItem, float64, error) {
	var invoice model.Invoice
	if err := s.db.First(&invoice, id).Error; err != nil {
		if errors.Is(err, gorm.ErrRecordNotFound) {
			return model.Invoice{}, nil, 0, status.Error(codes.NotFound, "invoice not found")
		}
		return model.Invoice{}, nil, 0, status.Error(codes.Internal, "failed to load invoice")
	}
	var items []model.InvoiceItem
	if err := s.db.Where("invoice_id = ?", invoice.ID).Order("id").Find(&items).Error; err != nil {
		return model.Invoice{}, nil, 0, status.Error(codes.Internal, "failed to load invoice items")
	}
	paid, err := paidAmount(s.db, invoice.ID)
	if err != nil {
		return model.Invoice{}, nil, 0, status.Error(codes.Internal, "failed to load payments")
	}
	return invoice, items, paid, nil
}

func protoInvoice(invoice model.Invoice, items []model.InvoiceItem, paid float64) *finance.Invoice {
	currency := invoice.Currency
	if currency == "" {
		currency = defaultCurrency
	}
	protoItems := make([]*finance.InvoiceItem, 0, len(items))
	if len(items) == 0 {
		protoItems = append(protoItems, &finance.InvoiceItem{
			Id:        strconv.FormatUint(uint64(invoice.ID), 10),
			InvoiceId: strconv.FormatUint(uint64(invoice.ID), 10),
			FeeTypeId: strconv.FormatUint(uint64(invoice.FeeTypeID), 10),
			Amount:    moneyFromFloat(invoice.Amount, currency),
			Quantity:  1,
			CreatedAt: timestamp(invoice.CreatedAt),
		})
	}
	for _, item := range items {
		protoItems = append(protoItems, &finance.InvoiceItem{
			Id:          strconv.FormatUint(uint64(item.ID), 10),
			InvoiceId:   strconv.FormatUint(uint64(item.InvoiceID), 10),
			FeeTypeId:   strconv.FormatUint(uint64(item.FeeTypeID), 10),
			Description: item.Description,
			Amount:      moneyFromFloat(item.Amount, currency),
			Quantity:    item.Quantity,
			CreatedAt:   timestamp(item.CreatedAt),
		})
	}
	remaining := invoice.Amount - paid
	if remaining < 0 {
		remaining = 0
	}
	return &finance.Invoice{
		Id:              strconv.FormatUint(uint64(invoice.ID), 10),
		StudentId:       strconv.FormatUint(uint64(invoice.StudentID), 10),
		UserId:          strconv.FormatUint(uint64(invoice.UserID), 10),
		Items:           protoItems,
		TotalAmount:     moneyFromFloat(invoice.Amount, currency),
		PaidAmount:      moneyFromFloat(paid, currency),
		RemainingAmount: moneyFromFloat(remaining, currency),
		Status:          invoiceStatusToProto(invoice.Status),
		Description:     invoice.Description,
		DueDate:         timestamp(invoice.DueDate),
		IssuedDate:      timestamp(invoice.CreatedAt),
		PaidDate:        optionalTimestamp(invoice.PaidAt),
		CreatedAt:       timestamp(invoice.CreatedAt),
		UpdatedAt:       timestamp(invoice.UpdatedAt),
	}
}

func (s *InvoiceServiceServer) GetInvoice(ctx context.Context, req *finance.GetInvoiceRequest) (*finance.GetInvoiceResponse, error) {
	claims, err := auth.AuthorizeGRPC(ctx, auth.PermViewInvoices)
	if err != nil {
		return nil, err
	}
	id, err := parseID(req.GetId(), "invoice ID")
	if err != nil {
		return nil, err
	}
	invoice, items, paid, err := s.loadInvoice(id)
	if err != nil {
		return nil, err
	}
	if !auth.CanAccessInvoiceGRPC(s.db, claims, &invoice) {
		return nil, status.Error(codes.PermissionDenied, "insufficient permission")
	}
	return &finance.GetInvoiceResponse{Invoice: protoInvoice(invoice, items, paid)}, nil
}

func paginate(total int64, req *common.PaginationRequest) *common.PaginationResponse {
	pageSize := int32(20)
	if req != nil && req.PageSize > 0 {
		pageSize = req.PageSize
	}
	if pageSize > 100 {
		pageSize = 100
	}
	currentPage := int32(1)
	if req != nil && req.Page > 0 {
		currentPage = req.Page
	}
	totalPages := int32(0)
	if total > 0 {
		totalPages = int32((total + int64(pageSize) - 1) / int64(pageSize))
	}
	return &common.PaginationResponse{
		TotalItems:  int32(total),
		TotalPages:  totalPages,
		CurrentPage: currentPage,
		PageSize:    pageSize,
		HasNext:     currentPage < totalPages,
		HasPrev:     currentPage > 1,
	}
}

func (s *InvoiceServiceServer) listInvoices(ctx context.Context, query *gorm.DB, pagination *common.PaginationRequest) (*finance.GetInvoicesResponse, error) {
	var total int64
	if err := query.Model(&model.Invoice{}).Count(&total).Error; err != nil {
		return nil, status.Error(codes.Internal, "failed to count invoices")
	}
	response := paginate(total, pagination)
	offset := int((response.CurrentPage - 1) * response.PageSize)
	var invoices []model.Invoice
	if err := query.Order("id").Offset(offset).Limit(int(response.PageSize)).Find(&invoices).Error; err != nil {
		return nil, status.Error(codes.Internal, "failed to list invoices")
	}
	protoInvoices := make([]*finance.Invoice, 0, len(invoices))
	for _, invoice := range invoices {
		_, items, paid, err := s.loadInvoice(invoice.ID)
		if err != nil {
			return nil, err
		}
		protoInvoices = append(protoInvoices, protoInvoice(invoice, items, paid))
	}
	return &finance.GetInvoicesResponse{Invoices: protoInvoices, Pagination: response}, nil
}

func (s *InvoiceServiceServer) GetInvoicesByStudent(ctx context.Context, req *finance.GetInvoicesByStudentRequest) (*finance.GetInvoicesResponse, error) {
	claims, err := auth.AuthorizeGRPC(ctx, auth.PermViewInvoices)
	if err != nil {
		return nil, err
	}
	studentID, err := parseID(req.GetStudentId(), "student ID")
	if err != nil {
		return nil, err
	}
	query := auth.FilterInvoicesGRPC(s.db, claims).Where("student_id = ?", studentID)
	return s.listInvoices(ctx, query, req.GetPagination())
}

func (s *InvoiceServiceServer) GetInvoicesByUser(ctx context.Context, req *finance.GetInvoicesByUserRequest) (*finance.GetInvoicesResponse, error) {
	claims, err := auth.AuthorizeGRPC(ctx, auth.PermViewInvoices)
	if err != nil {
		return nil, err
	}
	userID, err := parseID(req.GetUserId(), "user ID")
	if err != nil {
		return nil, err
	}
	query := auth.FilterInvoicesGRPC(s.db, claims).Where("user_id = ?", userID)
	return s.listInvoices(ctx, query, req.GetPagination())
}

func (s *InvoiceServiceServer) CreateInvoice(ctx context.Context, req *finance.CreateInvoiceRequest) (*finance.CreateInvoiceResponse, error) {
	claims, err := auth.AuthorizeGRPC(ctx, auth.PermManageInvoices)
	if err != nil {
		return nil, err
	}
	studentID, err := parseID(req.GetStudentId(), "student ID")
	if err != nil {
		return nil, err
	}
	var userID uint
	if strings.TrimSpace(req.GetUserId()) != "" {
		userID, err = parseID(req.GetUserId(), "user ID")
		if err != nil {
			return nil, err
		}
	}
	if len(req.GetItems()) == 0 {
		return nil, status.Error(codes.InvalidArgument, "at least one invoice item is required")
	}
	currency := defaultCurrency
	total := 0.0
	feeTypeIDs := make(map[uint]bool)
	items := make([]model.InvoiceItem, 0, len(req.GetItems()))
	for _, item := range req.GetItems() {
		amount, itemCurrency, err := moneyToFloat(item.GetAmount())
		if err != nil {
			return nil, err
		}
		if itemCurrency == defaultCurrency && currency == defaultCurrency {
			currency = itemCurrency
		} else if itemCurrency != currency {
			return nil, status.Error(codes.InvalidArgument, "all invoice items must use the same currency")
		}
		if item.GetQuantity() <= 0 {
			return nil, status.Error(codes.InvalidArgument, "invoice item quantity must be positive")
		}
		feeTypeID, err := parseID(item.GetFeeTypeId(), "fee type ID")
		if err != nil {
			return nil, err
		}
		var feeType model.FeeType
		if err := s.db.First(&feeType, feeTypeID).Error; err != nil {
			if errors.Is(err, gorm.ErrRecordNotFound) {
				return nil, status.Error(codes.NotFound, "fee type not found")
			}
			return nil, status.Error(codes.Internal, "failed to validate fee type")
		}
		feeTypeIDs[feeTypeID] = true
		lineTotal := amount * float64(item.GetQuantity())
		total += lineTotal
		items = append(items, model.InvoiceItem{
			FeeTypeID:   feeTypeID,
			Description: item.GetDescription(),
			Amount:      amount,
			Quantity:    item.GetQuantity(),
		})
	}
	if total <= 0 {
		return nil, status.Error(codes.InvalidArgument, "invoice total must be positive")
	}
	invoice := model.Invoice{
		StudentID:   studentID,
		UserID:      userID,
		Amount:      total,
		Currency:    currency,
		Status:      "pending",
		Description: req.GetDescription(),
		DueDate:     time.Now(),
	}
	if req.GetDueDate() != nil {
		invoice.DueDate = req.GetDueDate().AsTime()
	}
	if !auth.CanAccessInvoiceGRPC(s.db, claims, &invoice) {
		return nil, status.Error(codes.PermissionDenied, "insufficient permission")
	}
	if err := s.db.Transaction(func(tx *gorm.DB) error {
		if err := tx.Create(&invoice).Error; err != nil {
			return err
		}
		for i := range items {
			items[i].InvoiceID = invoice.ID
			if err := tx.Create(&items[i]).Error; err != nil {
				return err
			}
		}
		return nil
	}); err != nil {
		return nil, status.Error(codes.Internal, "failed to create invoice")
	}
	if s.publisher != nil {
		if err := s.publisher.Publish(ctx, "finance.invoice.created", map[string]any{
			"event":      "finance.invoice.created",
			"invoice_id": invoice.ID,
			"student_id": invoice.StudentID,
			"user_id":    invoice.UserID,
			"amount":     invoice.Amount,
			"currency":   invoice.Currency,
		}); err != nil {
			log.Printf("finance invoice event publish failed: invoice=%d: %v", invoice.ID, err)
		}
	}
	created, createdItems, paid, err := s.loadInvoice(invoice.ID)
	if err != nil {
		return nil, err
	}
	return &finance.CreateInvoiceResponse{Invoice: protoInvoice(created, createdItems, paid)}, nil
}

func (s *InvoiceServiceServer) UpdateInvoiceStatus(ctx context.Context, req *finance.UpdateInvoiceStatusRequest) (*finance.UpdateInvoiceStatusResponse, error) {
	claims, err := auth.AuthorizeGRPC(ctx, auth.PermManageInvoices)
	if err != nil {
		return nil, err
	}
	id, err := parseID(req.GetId(), "invoice ID")
	if err != nil {
		return nil, err
	}
	modelStatus, err := invoiceStatusToModel(req.GetStatus())
	if err != nil {
		return nil, err
	}
	invoice, _, paid, err := s.loadInvoice(id)
	if err != nil {
		return nil, err
	}
	if !auth.CanAccessInvoiceGRPC(s.db, claims, &invoice) {
		return nil, status.Error(codes.PermissionDenied, "insufficient permission")
	}
	if modelStatus == "paid" && paid+0.000001 < invoice.Amount {
		return nil, status.Error(codes.FailedPrecondition, "invoice cannot be marked paid before it is fully paid")
	}
	updates := map[string]any{"status": modelStatus}
	if modelStatus == "paid" {
		now := time.Now()
		updates["paid_at"] = now
	} else {
		updates["paid_at"] = nil
	}
	if err := s.db.Model(&model.Invoice{}).Where("id = ?", invoice.ID).Updates(updates).Error; err != nil {
		return nil, status.Error(codes.Internal, "failed to update invoice")
	}
	updated, items, paidTotal, err := s.loadInvoice(invoice.ID)
	if err != nil {
		return nil, err
	}
	if s.publisher != nil {
		if err := s.publisher.Publish(ctx, "finance.invoice.status_changed", map[string]any{
			"event":      "finance.invoice.status_changed",
			"invoice_id": updated.ID,
			"status":     updated.Status,
			"reason":     req.GetReason(),
		}); err != nil {
			log.Printf("finance status event publish failed: invoice=%d: %v", updated.ID, err)
		}
	}
	return &finance.UpdateInvoiceStatusResponse{Invoice: protoInvoice(updated, items, paidTotal), Success: true}, nil
}

func (s *InvoiceServiceServer) GetFeeTypes(ctx context.Context, req *finance.GetFeeTypesRequest) (*finance.GetFeeTypesResponse, error) {
	if _, err := auth.AuthorizeGRPC(ctx, auth.PermViewInvoices); err != nil {
		return nil, err
	}
	query := s.db.Model(&model.FeeType{})
	if req.GetActiveOnly() {
		query = query.Where("is_active = ?", true)
	}
	var total int64
	if err := query.Count(&total).Error; err != nil {
		return nil, status.Error(codes.Internal, "failed to count fee types")
	}
	pagination := paginate(total, req.GetPagination())
	offset := int((pagination.CurrentPage - 1) * pagination.PageSize)
	var feeTypes []model.FeeType
	if err := query.Order("id").Offset(offset).Limit(int(pagination.PageSize)).Find(&feeTypes).Error; err != nil {
		return nil, status.Error(codes.Internal, "failed to list fee types")
	}
	protoFeeTypes := make([]*finance.FeeType, 0, len(feeTypes))
	for _, feeType := range feeTypes {
		protoFeeTypes = append(protoFeeTypes, &finance.FeeType{
			Id:            strconv.FormatUint(uint64(feeType.ID), 10),
			Name:          feeType.Name,
			Description:   feeType.Description,
			DefaultAmount: moneyFromFloat(feeType.Amount, feeType.Currency),
			IsActive:      feeType.IsActive,
			CreatedAt:     timestamp(feeType.CreatedAt),
			UpdatedAt:     timestamp(feeType.UpdatedAt),
		})
	}
	return &finance.GetFeeTypesResponse{FeeTypes: protoFeeTypes, Pagination: pagination}, nil
}

type PaymentServiceServer struct {
	finance.UnimplementedPaymentServiceServer
	db        *gorm.DB
	publisher events.Publisher
}

func NewPaymentServiceServer(db *gorm.DB, publisher events.Publisher) *PaymentServiceServer {
	return &PaymentServiceServer{db: db, publisher: publisher}
}

func protoPayment(payment model.Payment) *finance.Payment {
	currency := payment.Currency
	if currency == "" {
		currency = defaultCurrency
	}
	return &finance.Payment{
		Id:             strconv.FormatUint(uint64(payment.ID), 10),
		InvoiceId:      strconv.FormatUint(uint64(payment.InvoiceID), 10),
		TransactionId:  payment.TransactionID,
		Amount:         moneyFromFloat(payment.Amount, currency),
		Method:         paymentMethodToProto(payment.Method),
		Status:         finance.PaymentStatus_PAYMENT_STATUS_COMPLETED,
		Description:    payment.Note,
		PaymentGateway: payment.Gateway,
		PaidAt:         timestamp(payment.CreatedAt),
		CreatedAt:      timestamp(payment.CreatedAt),
		UpdatedAt:      timestamp(payment.UpdatedAt),
	}
}

func (s *PaymentServiceServer) CreatePayment(ctx context.Context, req *finance.CreatePaymentRequest) (*finance.CreatePaymentResponse, error) {
	claims, err := auth.AuthorizeGRPC(ctx, auth.PermManagePayments)
	if err != nil {
		return nil, err
	}
	invoiceID, err := parseID(req.GetInvoiceId(), "invoice ID")
	if err != nil {
		return nil, err
	}
	amount, currency, err := moneyToFloat(req.GetAmount())
	if err != nil {
		return nil, err
	}
	if amount <= 0 {
		return nil, status.Error(codes.InvalidArgument, "payment amount must be positive")
	}
	method, err := paymentMethodToModel(req.GetMethod())
	if err != nil {
		return nil, err
	}
	var invoice model.Invoice
	if err := s.db.First(&invoice, invoiceID).Error; err != nil {
		if errors.Is(err, gorm.ErrRecordNotFound) {
			return nil, status.Error(codes.NotFound, "invoice not found")
		}
		return nil, status.Error(codes.Internal, "failed to load invoice")
	}
	if !auth.CanAccessInvoiceGRPC(s.db, claims, &invoice) {
		return nil, status.Error(codes.PermissionDenied, "insufficient permission")
	}
	invoiceCurrency := invoice.Currency
	if invoiceCurrency == "" {
		invoiceCurrency = defaultCurrency
	}
	if currency != invoiceCurrency {
		return nil, status.Error(codes.InvalidArgument, "payment currency must match invoice currency")
	}
	paid, err := paidAmount(s.db, invoice.ID)
	if err != nil {
		return nil, status.Error(codes.Internal, "failed to load payments")
	}
	if paid+amount > invoice.Amount+0.000001 {
		return nil, status.Error(codes.FailedPrecondition, "payment amount exceeds remaining invoice balance")
	}
	payment := model.Payment{
		InvoiceID:     invoice.ID,
		Amount:        amount,
		Currency:      currency,
		Method:        method,
		Status:        "completed",
		TransactionID: req.GetTransactionId(),
		Gateway:       req.GetPaymentGateway(),
		Note:          req.GetDescription(),
		PaidBy:        claims.UserID,
	}
	now := time.Now()
	if err := s.db.Transaction(func(tx *gorm.DB) error {
		if err := tx.Create(&payment).Error; err != nil {
			return err
		}
		newPaid := paid + amount
		invoiceStatus := "pending"
		updates := map[string]any{"status": invoiceStatus}
		if newPaid+0.000001 >= invoice.Amount {
			invoiceStatus = "paid"
			updates["status"] = invoiceStatus
			updates["paid_at"] = now
		}
		return tx.Model(&model.Invoice{}).Where("id = ?", invoice.ID).Updates(updates).Error
	}); err != nil {
		return nil, status.Error(codes.Internal, "failed to create payment")
	}
	if s.publisher != nil {
		if err := s.publisher.Publish(ctx, "finance.payment.received", map[string]any{
			"event":      "finance.payment.received",
			"invoice_id": invoice.ID,
			"payment_id": payment.ID,
			"amount":     payment.Amount,
			"currency":   payment.Currency,
		}); err != nil {
			log.Printf("finance payment event publish failed: invoice=%d payment=%d: %v", invoice.ID, payment.ID, err)
		}
	}
	updated, items, paidTotal, err := s.loadInvoiceForPayment(invoice.ID)
	if err != nil {
		return nil, err
	}
	return &finance.CreatePaymentResponse{Payment: protoPayment(payment), UpdatedInvoice: protoInvoice(updated, items, paidTotal)}, nil
}

func (s *PaymentServiceServer) loadInvoiceForPayment(id uint) (model.Invoice, []model.InvoiceItem, float64, error) {
	invoiceServer := &InvoiceServiceServer{db: s.db, publisher: s.publisher}
	return invoiceServer.loadInvoice(id)
}

func (s *PaymentServiceServer) GetPaymentsByInvoice(ctx context.Context, req *finance.GetPaymentsByInvoiceRequest) (*finance.GetPaymentsResponse, error) {
	claims, err := auth.AuthorizeGRPC(ctx, auth.PermViewInvoices)
	if err != nil {
		return nil, err
	}
	invoiceID, err := parseID(req.GetInvoiceId(), "invoice ID")
	if err != nil {
		return nil, err
	}
	var invoice model.Invoice
	if err := s.db.First(&invoice, invoiceID).Error; err != nil {
		if errors.Is(err, gorm.ErrRecordNotFound) {
			return nil, status.Error(codes.NotFound, "invoice not found")
		}
		return nil, status.Error(codes.Internal, "failed to load invoice")
	}
	if !auth.CanAccessInvoiceGRPC(s.db, claims, &invoice) {
		return nil, status.Error(codes.PermissionDenied, "insufficient permission")
	}
	var payments []model.Payment
	if err := s.db.Where("invoice_id = ?", invoice.ID).Order("id").Find(&payments).Error; err != nil {
		return nil, status.Error(codes.Internal, "failed to list payments")
	}
	protoPayments := make([]*finance.Payment, 0, len(payments))
	for _, payment := range payments {
		protoPayments = append(protoPayments, protoPayment(payment))
	}
	return &finance.GetPaymentsResponse{Payments: protoPayments}, nil
}

type FinanceUserServiceServer struct {
	finance.UnimplementedFinanceUserServiceServer
	db *gorm.DB
}

func NewFinanceUserServiceServer(db *gorm.DB) *FinanceUserServiceServer {
	return &FinanceUserServiceServer{db: db}
}

func (s *FinanceUserServiceServer) GetUser(ctx context.Context, req *common.UUID) (*common.UserRef, error) {
	if _, err := auth.AuthorizeGRPC(ctx, auth.PermViewUsers); err != nil {
		return nil, err
	}
	id, err := parseID(req.GetValue(), "user ID")
	if err != nil {
		return nil, err
	}
	var user model.UserReadModel
	if err := s.db.First(&user, id).Error; err != nil {
		if errors.Is(err, gorm.ErrRecordNotFound) {
			return nil, status.Error(codes.NotFound, "user not found")
		}
		return nil, status.Error(codes.Internal, "failed to load user")
	}
	role := ""
	if user.Roles != "" {
		var roles []string
		if err := json.Unmarshal([]byte(user.Roles), &roles); err == nil && len(roles) > 0 {
			role = strings.Join(roles, ",")
		} else {
			role = user.Roles
		}
	}
	return &common.UserRef{
		Id:       strconv.FormatUint(uint64(user.ID), 10),
		Email:    user.Email,
		FullName: user.Name,
		Role:     role,
	}, nil
}

func RegisterServices(grpcServer *grpc.Server, db *gorm.DB, publisher events.Publisher) {
	invoiceService := NewInvoiceServiceServer(db, publisher)
	paymentService := NewPaymentServiceServer(db, publisher)
	userService := NewFinanceUserServiceServer(db)

	finance.RegisterInvoiceServiceServer(grpcServer, invoiceService)
	finance.RegisterPaymentServiceServer(grpcServer, paymentService)
	finance.RegisterFinanceUserServiceServer(grpcServer, userService)
}
