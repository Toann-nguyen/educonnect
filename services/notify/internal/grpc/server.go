package grpc

import (
	"context"
	"encoding/json"
	"fmt"
	"log"
	"net/smtp"
	"os"

	"educonnect/notify/internal/template"
	pb "educonnect/internal/pkg/proto/notify"
	"google.golang.org/grpc/codes"
	"google.golang.org/grpc/status"
)

// Server implements pb.NotifyServiceServer
type Server struct {
	pb.UnimplementedNotifyServiceServer
	templateStore *template.Store
	mailHost      string
	mailPort      string
	mailUser      string
	mailPass      string
	mailFrom      string
}

// NewServer creates a new Notify gRPC server
func NewServer(store *template.Store) *Server {
	return &Server{
		templateStore: store,
		mailHost:      getEnv("MAIL_HOST", "smtp.gmail.com"),
		mailPort:      getEnv("MAIL_PORT", "587"),
		mailUser:      getEnv("MAIL_USERNAME", ""),
		mailPass:      getEnv("MAIL_PASSWORD", ""),
		mailFrom:      getEnv("MAIL_FROM", "noreply@educonnect.com"),
	}
}

func getEnv(key, defaultValue string) string {
	if value := os.Getenv(key); value != "" {
		return value
	}
	return defaultValue
}

// SendEmail sends an email via gRPC
func (s *Server) SendEmail(ctx context.Context, req *pb.SendEmailRequest) (*pb.SendEmailResponse, error) {
	log.Printf("[gRPC] SendEmail request: to=%s, subject=%s", req.To, req.Subject)

	if req.To == "" {
		return nil, status.Error(codes.InvalidArgument, "recipient email is required")
	}

	if req.Body == "" && req.TemplateId != "" {
		// Load template from store
		tmpl, err := s.templateStore.Get(req.TemplateId)
		if err != nil {
			return nil, status.Errorf(codes.NotFound, "template not found: %v", err)
		}
		req.Body = tmpl.Content
		if req.Subject == "" {
			req.Subject = tmpl.Subject
		}
	}

	if req.Body == "" {
		return nil, status.Error(codes.InvalidArgument, "email body or template_id is required")
	}

	// Build email message
	msg := []byte(fmt.Sprintf(
		"From: %s\r\nTo: %s\r\nSubject: %s\r\nMIME-version: 1.0;\r\nContent-Type: text/html; charset=\"UTF-8\";\r\n\r\n%s",
		s.mailFrom, req.To, req.Subject, req.Body,
	))

	addr := fmt.Sprintf("%s:%s", s.mailHost, s.mailPort)
	auth := smtp.PlainAuth("", s.mailUser, s.mailPass, s.mailHost)

	err := smtp.SendMail(addr, auth, s.mailFrom, []string{req.To}, msg)
	if err != nil {
		log.Printf("[gRPC] SendEmail failed: %v", err)
		return nil, status.Errorf(codes.Internal, "failed to send email: %v", err)
	}

	log.Printf("[gRPC] SendEmail success: to=%s", req.To)
	return &pb.SendEmailResponse{
		Success: true,
		Message: "Email sent successfully",
	}, nil
}

// SendSMS sends an SMS via gRPC (placeholder - integrate with SMS provider)
func (s *Server) SendSMS(ctx context.Context, req *pb.SendSMSRequest) (*pb.SendSMSResponse, error) {
	log.Printf("[gRPC] SendSMS request: to=%s, message=%s", req.To, req.Message)

	if req.To == "" {
		return nil, status.Error(codes.InvalidArgument, "recipient phone number is required")
	}

	if req.Message == "" {
		return nil, status.Error(codes.InvalidArgument, "message content is required")
	}

	// TODO: Integrate with actual SMS provider (Twilio, Vonage, etc.)
	// For now, log and return success
	log.Printf("[gRPC] SendSMS simulated: to=%s", req.To)

	return &pb.SendSMSResponse{
		Success: true,
		Message: "SMS queued for delivery (simulated)",
	}, nil
}

// SendNotification sends a generic notification (email or SMS)
func (s *Server) SendNotification(ctx context.Context, req *pb.SendNotificationRequest) (*pb.SendNotificationResponse, error) {
	log.Printf("[gRPC] SendNotification request: user_id=%d, type=%s", req.UserId, req.Type)

	var emailSuccess, smsSuccess bool
	var messages []string

	// Send email if requested
	if req.Type == pb.NotificationType_NOTIFICATION_TYPE_EMAIL || req.Type == pb.NotificationType_NOTIFICATION_TYPE_BOTH {
		if req.Email != "" {
			emailReq := &pb.SendEmailRequest{
				To:         req.Email,
				Subject:    req.Subject,
				Body:       req.Body,
				TemplateId: req.TemplateId,
				Metadata:   req.Metadata,
			}
			emailRes, err := s.SendEmail(ctx, emailReq)
			if err != nil {
				messages = append(messages, fmt.Sprintf("Email failed: %v", err))
			} else {
				emailSuccess = true
				messages = append(messages, emailRes.Message)
			}
		}
	}

	// Send SMS if requested
	if req.Type == pb.NotificationType_NOTIFICATION_TYPE_SMS || req.Type == pb.NotificationType_NOTIFICATION_TYPE_BOTH {
		if req.Phone != "" {
			smsReq := &pb.SendSMSRequest{
				To:      req.Phone,
				Message: req.Body,
			}
			smsRes, err := s.SendSMS(ctx, smsReq)
			if err != nil {
				messages = append(messages, fmt.Sprintf("SMS failed: %v", err))
			} else {
				smsSuccess = true
				messages = append(messages, smsRes.Message)
			}
		}
	}

	if !emailSuccess && !smsSuccess {
		return nil, status.Errorf(codes.FailedPrecondition, "all notification channels failed: %v", messages)
	}

	return &pb.SendNotificationResponse{
		Success:      true,
		EmailSent:    emailSuccess,
		SmsSent:      smsSuccess,
		Messages:     messages,
		Notification: req,
	}, nil
}

// BroadcastNotification sends notifications to multiple users
func (s *Server) BroadcastNotification(ctx context.Context, req *pb.BroadcastNotificationRequest) (*pb.BroadcastNotificationResponse, error) {
	log.Printf("[gRPC] BroadcastNotification request: %d recipients", len(req.Recipients))

	results := make([]*pb.NotificationResult, 0, len(req.Recipients))
	successCount := 0
	failCount := 0

	for _, recipient := range req.Recipients {
		notifyReq := &pb.SendNotificationRequest{
			UserId:     recipient.UserId,
			Email:      recipient.Email,
			Phone:      recipient.Phone,
			Type:       req.Type,
			Subject:    req.Subject,
			Body:       req.Body,
			TemplateId: req.TemplateId,
			Metadata:   req.Metadata,
		}

		res, err := s.SendNotification(ctx, notifyReq)
		if err != nil {
			failCount++
			results = append(results, &pb.NotificationResult{
				UserId:  recipient.UserId,
				Success: false,
				Error:   err.Error(),
			})
		} else {
			successCount++
			results = append(results, &pb.NotificationResult{
				UserId:     recipient.UserId,
				Success:    true,
				EmailSent:  res.EmailSent,
				SmsSent:    res.SmsSent,
				Message:    res.Messages[0],
			})
		}
	}

	log.Printf("[gRPC] BroadcastNotification completed: success=%d, failed=%d", successCount, failCount)

	return &pb.BroadcastNotificationResponse{
		TotalRecipients: int32(len(req.Recipients)),
		SuccessCount:    int32(successCount),
		FailCount:       int32(failCount),
		Results:         results,
	}, nil
}

// GetTemplate retrieves a notification template
func (s *Server) GetTemplate(ctx context.Context, req *pb.GetTemplateRequest) (*pb.GetTemplateResponse, error) {
	log.Printf("[gRPC] GetTemplate request: id=%s", req.Id)

	tmpl, err := s.templateStore.Get(req.Id)
	if err != nil {
		return nil, status.Errorf(codes.NotFound, "template not found: %v", err)
	}

	return &pb.GetTemplateResponse{
		Template: &pb.NotificationTemplate{
			Id:        tmpl.ID,
			Name:      tmpl.Name,
			Type:      tmpl.Type,
			Subject:   tmpl.Subject,
			Content:   tmpl.Content,
			Variables: tmpl.Variables,
			CreatedAt: tmpl.CreatedAt,
			UpdatedAt: tmpl.UpdatedAt,
		},
	}, nil
}

// ListTemplates lists all notification templates
func (s *Server) ListTemplates(ctx context.Context, req *pb.ListTemplatesRequest) (*pb.ListTemplatesResponse, error) {
	log.Printf("[gRPC] ListTemplates request")

	templates := s.templateStore.List()
	pbTemplates := make([]*pb.NotificationTemplate, 0, len(templates))

	for _, tmpl := range templates {
		pbTemplates = append(pbTemplates, &pb.NotificationTemplate{
			Id:        tmpl.ID,
			Name:      tmpl.Name,
			Type:      tmpl.Type,
			Subject:   tmpl.Subject,
			Content:   tmpl.Content,
			Variables: tmpl.Variables,
			CreatedAt: tmpl.CreatedAt,
			UpdatedAt: tmpl.UpdatedAt,
		})
	}

	return &pb.ListTemplatesResponse{
		Templates: pbTemplates,
		Total:     int32(len(templates)),
	}, nil
}

// CreateTemplate creates a new notification template
func (s *Server) CreateTemplate(ctx context.Context, req *pb.CreateTemplateRequest) (*pb.CreateTemplateResponse, error) {
	log.Printf("[gRPC] CreateTemplate request: name=%s", req.Name)

	tmpl := &template.NotificationTemplate{
		ID:        req.Name, // Use name as ID for simplicity
		Name:      req.Name,
		Type:      req.Type,
		Subject:   req.Subject,
		Content:   req.Content,
		Variables: req.Variables,
	}

	if err := s.templateStore.Save(tmpl); err != nil {
		return nil, status.Errorf(codes.Internal, "failed to save template: %v", err)
	}

	log.Printf("[gRPC] CreateTemplate success: id=%s", tmpl.ID)

	return &pb.CreateTemplateResponse{
		Template: &pb.NotificationTemplate{
			Id:        tmpl.ID,
			Name:      tmpl.Name,
			Type:      tmpl.Type,
			Subject:   tmpl.Subject,
			Content:   tmpl.Content,
			Variables: tmpl.Variables,
			CreatedAt: tmpl.CreatedAt,
			UpdatedAt: tmpl.UpdatedAt,
		},
	}, nil
}

// UpdateTemplate updates an existing notification template
func (s *Server) UpdateTemplate(ctx context.Context, req *pb.UpdateTemplateRequest) (*pb.UpdateTemplateResponse, error) {
	log.Printf("[gRPC] UpdateTemplate request: id=%s", req.Id)

	tmpl, err := s.templateStore.Get(req.Id)
	if err != nil {
		return nil, status.Errorf(codes.NotFound, "template not found: %v", err)
	}

	// Update fields
	if req.Name != "" {
		tmpl.Name = req.Name
	}
	if req.Type != "" {
		tmpl.Type = req.Type
	}
	if req.Subject != "" {
		tmpl.Subject = req.Subject
	}
	if req.Content != "" {
		tmpl.Content = req.Content
	}
	if len(req.Variables) > 0 {
		tmpl.Variables = req.Variables
	}

	if err := s.templateStore.Save(tmpl); err != nil {
		return nil, status.Errorf(codes.Internal, "failed to update template: %v", err)
	}

	log.Printf("[gRPC] UpdateTemplate success: id=%s", tmpl.ID)

	return &pb.UpdateTemplateResponse{
		Template: &pb.NotificationTemplate{
			Id:        tmpl.ID,
			Name:      tmpl.Name,
			Type:      tmpl.Type,
			Subject:   tmpl.Subject,
			Content:   tmpl.Content,
			Variables: tmpl.Variables,
			CreatedAt: tmpl.CreatedAt,
			UpdatedAt: tmpl.UpdatedAt,
		},
	}, nil
}

// DeleteTemplate deletes a notification template
func (s *Server) DeleteTemplate(ctx context.Context, req *pb.DeleteTemplateRequest) (*pb.DeleteTemplateResponse, error) {
	log.Printf("[gRPC] DeleteTemplate request: id=%s", req.Id)

	if err := s.templateStore.Delete(req.Id); err != nil {
		return nil, status.Errorf(codes.Internal, "failed to delete template: %v", err)
	}

	log.Printf("[gRPC] DeleteTemplate success: id=%s", req.Id)

	return &pb.DeleteTemplateResponse{
		Success: true,
		Message: "Template deleted successfully",
	}, nil
}

// HealthCheck returns the health status of the Notify service
func (s *Server) HealthCheck(ctx context.Context, req *pb.HealthCheckRequest) (*pb.HealthCheckResponse, error) {
	return &pb.HealthCheckResponse{
		Status:  "SERVING",
		Service: "notify-service",
		Version: "1.0.0",
	}, nil
}
