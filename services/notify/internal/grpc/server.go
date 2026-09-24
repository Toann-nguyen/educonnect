package grpc

import (
	"context"
	"fmt"
	"log"
	"net/smtp"
	"os"
	"strings"
	"sync"
	"sync/atomic"
	"time"

	common "educonnect/internal/pkg/proto/common"
	pb "educonnect/internal/pkg/proto/notify"
	"educonnect/notify/internal/template"
	"google.golang.org/grpc/codes"
	"google.golang.org/grpc/status"
	"google.golang.org/protobuf/types/known/timestamppb"
)

type Server struct {
	pb.UnimplementedNotificationServiceServer
	pb.UnimplementedNotificationEventServiceServer
	templateStore *template.Store
	mailHost      string
	mailPort      string
	mailUser      string
	mailPass      string
	mailFrom      string

	mu            sync.Mutex
	sequence      atomic.Uint64
	notifications map[string]*pb.Notification
	order         []string
}

func NewServer(store *template.Store) *Server {
	return &Server{
		templateStore: store,
		mailHost:      getEnv("MAIL_HOST", "smtp.gmail.com"),
		mailPort:      getEnv("MAIL_PORT", "587"),
		mailUser:      getEnv("MAIL_USERNAME", ""),
		mailPass:      getEnv("MAIL_PASSWORD", ""),
		mailFrom:      getEnv("MAIL_FROM", "noreply@educonnect.com"),
		notifications: make(map[string]*pb.Notification),
	}
}

func getEnv(key, defaultValue string) string {
	if value := os.Getenv(key); value != "" {
		return value
	}
	return defaultValue
}

func (s *Server) nextID() string {
	return fmt.Sprintf("ntf-%d", s.sequence.Add(1))
}

func (s *Server) record(notification *pb.Notification) *pb.Notification {
	now := timestamppb.Now()
	notification.Id = s.nextID()
	notification.CreatedAt = now
	if notification.Metadata == nil {
		notification.Metadata = make(map[string]string)
	}
	s.mu.Lock()
	defer s.mu.Unlock()
	s.notifications[notification.Id] = notification
	s.order = append(s.order, notification.Id)
	return notification
}

func (s *Server) deliverEmail(to, subject, body string) error {
	if to == "" {
		return status.Error(codes.InvalidArgument, "recipient email is required")
	}
	if body == "" {
		return status.Error(codes.InvalidArgument, "email body is required")
	}
	message := []byte(fmt.Sprintf(
		"From: %s\r\nTo: %s\r\nSubject: %s\r\nMIME-version: 1.0;\r\nContent-Type: text/html; charset=\"UTF-8\";\r\n\r\n%s",
		s.mailFrom, to, subject, body,
	))
	address := fmt.Sprintf("%s:%s", s.mailHost, s.mailPort)
	if err := smtp.SendMail(address, smtp.PlainAuth("", s.mailUser, s.mailPass, s.mailHost), s.mailFrom, []string{to}, message); err != nil {
		return status.Errorf(codes.Internal, "failed to send email: %v", err)
	}
	return nil
}

func renderTemplate(body string, variables map[string]string) string {
	for key, value := range variables {
		body = strings.ReplaceAll(body, "{{"+key+"}}", value)
	}
	return body
}

func (s *Server) deliver(req *pb.SendNotificationRequest) (*pb.Notification, error) {
	if req == nil {
		return nil, status.Error(codes.InvalidArgument, "notification request is required")
	}
	if req.UserId == "" {
		return nil, status.Error(codes.InvalidArgument, "user id is required")
	}

	notification := &pb.Notification{
		UserId:   req.UserId,
		Email:    req.Email,
		Phone:    req.Phone,
		Title:    req.Title,
		Message:  req.Message,
		Type:     req.Type,
		Priority: req.Priority,
		Metadata: req.Metadata,
		Status:   pb.NotificationStatus_NOTIFICATION_STATUS_PENDING,
	}

	switch req.Type {
	case pb.NotificationType_NOTIFICATION_TYPE_EMAIL:
		if err := s.deliverEmail(req.Email, req.Title, req.Message); err != nil {
			notification.Status = pb.NotificationStatus_NOTIFICATION_STATUS_FAILED
			notification.ErrorMessage = err.Error()
			s.record(notification)
			return nil, err
		}
		notification.Status = pb.NotificationStatus_NOTIFICATION_STATUS_SENT
		notification.SentAt = timestamppb.Now()
		return s.record(notification), nil
	case pb.NotificationType_NOTIFICATION_TYPE_SMS:
		if req.Phone == "" {
			return nil, status.Error(codes.InvalidArgument, "recipient phone number is required")
		}
		if req.Message == "" {
			return nil, status.Error(codes.InvalidArgument, "message content is required")
		}
		log.Printf("[gRPC] SMS queued for delivery (simulated): to=%s", req.Phone)
		notification.Status = pb.NotificationStatus_NOTIFICATION_STATUS_SENT
		notification.SentAt = timestamppb.Now()
		notification.ErrorMessage = "SMS provider integration pending; delivery simulated"
		return s.record(notification), nil
	case pb.NotificationType_NOTIFICATION_TYPE_PUSH, pb.NotificationType_NOTIFICATION_TYPE_IN_APP:
		return s.record(notification), nil
	default:
		return nil, status.Error(codes.InvalidArgument, "supported notification type is required")
	}
}

func (s *Server) SendNotification(_ context.Context, req *pb.SendNotificationRequest) (*pb.SendNotificationResponse, error) {
	notification, err := s.deliver(req)
	if err != nil {
		return nil, err
	}
	return &pb.SendNotificationResponse{
		Notification: notification,
		Success:      true,
		Message:      "Notification processed successfully",
	}, nil
}

func (s *Server) SendTemplateNotification(ctx context.Context, req *pb.SendTemplateNotificationRequest) (*pb.SendNotificationResponse, error) {
	if req == nil || req.UserId == "" {
		return nil, status.Error(codes.InvalidArgument, "user id is required")
	}
	if req.TemplateId == "" {
		return nil, status.Error(codes.InvalidArgument, "template id is required")
	}
	tmpl, err := s.templateStore.Get(ctx, req.TemplateId)
	if err != nil {
		return nil, status.Errorf(codes.Internal, "failed to load template: %v", err)
	}
	if tmpl == nil {
		return nil, status.Errorf(codes.NotFound, "template not found: %s", req.TemplateId)
	}

	notificationType := pb.NotificationType_NOTIFICATION_TYPE_EMAIL
	channels := make(map[string]bool, len(tmpl.Channels))
	for _, channel := range tmpl.Channels {
		channels[strings.ToLower(channel)] = true
	}
	switch {
	case channels["email"] && channels["sms"] && req.Email == "":
		notificationType = pb.NotificationType_NOTIFICATION_TYPE_SMS
	case channels["email"] || (req.Email != "" && req.Phone == ""):
		notificationType = pb.NotificationType_NOTIFICATION_TYPE_EMAIL
	case channels["sms"]:
		notificationType = pb.NotificationType_NOTIFICATION_TYPE_SMS
	case req.Phone != "" && req.Email == "":
		notificationType = pb.NotificationType_NOTIFICATION_TYPE_SMS
	}

	delivery, err := s.deliver(&pb.SendNotificationRequest{
		UserId:   req.UserId,
		Email:    req.Email,
		Phone:    req.Phone,
		Title:    tmpl.Subject,
		Message:  renderTemplate(tmpl.Body, req.Variables),
		Type:     notificationType,
		Priority: req.Priority,
		Metadata: req.Variables,
	})
	if err != nil {
		return nil, err
	}
	delivery.TemplateId = req.TemplateId
	return &pb.SendNotificationResponse{
		Notification: delivery,
		Success:      true,
		Message:      "Template notification processed successfully",
	}, nil
}

func (s *Server) SendBulkNotifications(_ context.Context, req *pb.SendBulkNotificationsRequest) (*pb.SendBulkNotificationsResponse, error) {
	if req == nil || len(req.UserIds) == 0 {
		return nil, status.Error(codes.InvalidArgument, "at least one user id is required")
	}
	response := &pb.SendBulkNotificationsResponse{}
	for _, userID := range req.UserIds {
		_, err := s.deliver(&pb.SendNotificationRequest{
			UserId:   userID,
			Title:    req.Title,
			Message:  req.Message,
			Type:     req.Type,
			Priority: req.Priority,
			Metadata: req.Metadata,
		})
		if err != nil {
			response.TotalFailed++
			response.FailedUserIds = append(response.FailedUserIds, userID)
		} else {
			response.TotalSent++
		}
	}
	return response, nil
}

func (s *Server) GetNotification(_ context.Context, req *pb.GetNotificationRequest) (*pb.GetNotificationResponse, error) {
	if req == nil || req.Id == "" {
		return nil, status.Error(codes.InvalidArgument, "notification id is required")
	}
	s.mu.Lock()
	defer s.mu.Unlock()
	notification, ok := s.notifications[req.Id]
	if !ok {
		return nil, status.Errorf(codes.NotFound, "notification not found: %s", req.Id)
	}
	return &pb.GetNotificationResponse{Notification: notification}, nil
}

func (s *Server) GetNotificationsByUser(_ context.Context, req *pb.GetNotificationsByUserRequest) (*pb.GetNotificationsResponse, error) {
	if req == nil || req.UserId == "" {
		return nil, status.Error(codes.InvalidArgument, "user id is required")
	}
	page := int(req.GetPagination().GetPage())
	if page < 1 {
		page = 1
	}
	pageSize := int(req.GetPagination().GetPageSize())
	if pageSize < 1 {
		pageSize = 20
	}
	if pageSize > 100 {
		pageSize = 100
	}

	s.mu.Lock()
	defer s.mu.Unlock()
	var filtered []*pb.Notification
	for _, id := range s.order {
		notification := s.notifications[id]
		if notification.UserId != req.UserId {
			continue
		}
		if req.Status != pb.NotificationStatus_NOTIFICATION_STATUS_UNSPECIFIED && notification.Status != req.Status {
			continue
		}
		if req.Type != pb.NotificationType_NOTIFICATION_TYPE_UNSPECIFIED && notification.Type != req.Type {
			continue
		}
		filtered = append(filtered, notification)
	}

	total := len(filtered)
	start := (page - 1) * pageSize
	if start > total {
		start = total
	}
	end := start + pageSize
	if end > total {
		end = total
	}
	totalPages := 0
	if total > 0 {
		totalPages = (total + pageSize - 1) / pageSize
	}
	return &pb.GetNotificationsResponse{
		Notifications: filtered[start:end],
		Pagination: &common.PaginationResponse{
			TotalItems:  int32(total),
			TotalPages:  int32(totalPages),
			CurrentPage: int32(page),
			PageSize:    int32(pageSize),
			HasNext:     page < totalPages,
			HasPrev:     page > 1,
		},
	}, nil
}

func (s *Server) markRead(userID, id string) (*pb.Notification, error) {
	s.mu.Lock()
	defer s.mu.Unlock()
	if id != "" {
		notification, ok := s.notifications[id]
		if !ok {
			return nil, status.Errorf(codes.NotFound, "notification not found: %s", id)
		}
		if userID != "" && notification.UserId != userID {
			return nil, status.Error(codes.PermissionDenied, "notification does not belong to user")
		}
		if notification.Metadata == nil {
			notification.Metadata = make(map[string]string)
		}
		notification.Metadata["read"] = "true"
		notification.Metadata["read_at"] = time.Now().UTC().Format(time.RFC3339)
		return notification, nil
	}
	marked := 0
	for _, notificationID := range s.order {
		notification := s.notifications[notificationID]
		if notification.UserId != userID {
			continue
		}
		if notification.Metadata == nil {
			notification.Metadata = make(map[string]string)
		}
		notification.Metadata["read"] = "true"
		notification.Metadata["read_at"] = time.Now().UTC().Format(time.RFC3339)
		marked++
	}
	if marked == 0 {
		return nil, status.Errorf(codes.NotFound, "no notifications found for user: %s", userID)
	}
	return &pb.Notification{UserId: userID}, nil
}

func (s *Server) MarkAsRead(_ context.Context, req *pb.MarkAsReadRequest) (*pb.MarkAsReadResponse, error) {
	if req == nil || req.Id == "" {
		return nil, status.Error(codes.InvalidArgument, "notification id is required")
	}
	if _, err := s.markRead("", req.Id); err != nil {
		return nil, err
	}
	return &pb.MarkAsReadResponse{Success: true, Message: "Notification marked as read"}, nil
}

func (s *Server) MarkAllAsRead(_ context.Context, req *pb.MarkAllAsReadRequest) (*pb.MarkAsReadResponse, error) {
	if req == nil || req.UserId == "" {
		return nil, status.Error(codes.InvalidArgument, "user id is required")
	}
	if _, err := s.markRead(req.UserId, ""); err != nil {
		return nil, err
	}
	return &pb.MarkAsReadResponse{Success: true, Message: "Notifications marked as read"}, nil
}

func (s *Server) ProcessInvoicePaymentEvent(_ context.Context, req *pb.ProcessInvoicePaymentEventRequest) (*pb.ProcessInvoicePaymentEventResponse, error) {
	if req == nil || req.Event == nil {
		return nil, status.Error(codes.InvalidArgument, "invoice payment event is required")
	}
	event := req.Event
	if event.UserId == "" || event.InvoiceId == "" {
		return nil, status.Error(codes.InvalidArgument, "invoice and user ids are required")
	}
	amount := ""
	if event.Amount != nil {
		amount = fmt.Sprintf("%d %s", event.Amount.Amount, event.Amount.Currency)
	}
	notification := s.record(&pb.Notification{
		UserId:  event.UserId,
		Title:   "Invoice payment received",
		Message: fmt.Sprintf("Invoice %s payment %s: %s", event.InvoiceId, event.PaymentStatus, strings.TrimSpace(amount)),
		Type:    pb.NotificationType_NOTIFICATION_TYPE_IN_APP,
		Status:  pb.NotificationStatus_NOTIFICATION_STATUS_SENT,
		Metadata: map[string]string{
			"invoice_id":     event.InvoiceId,
			"student_id":     event.StudentId,
			"payment_status": event.PaymentStatus,
		},
		SentAt: timestamppb.Now(),
	})
	return &pb.ProcessInvoicePaymentEventResponse{
		Success:       true,
		Message:       "Invoice payment event processed",
		Notifications: []*pb.Notification{notification},
	}, nil
}
