package grpc

import (
	"context"
	"log"
	"time"

	pb "educonnect/internal/pkg/proto/notify"
	"google.golang.org/grpc"
	"google.golang.org/grpc/metadata"
)

// Client wraps the gRPC client for Notify service
type Client struct {
	conn     *grpc.ClientConn
	client   pb.NotifyServiceClient
	timeout  time.Duration
}

// NewClient creates a new Notify gRPC client
func NewClient(target string, opts ...grpc.DialOption) (*Client, error) {
	defaultOpts := []grpc.DialOption{
		grpc.WithInsecure(), // Remove in production, use WithTransportCredentials
		grpc.WithBlock(),
	}
	defaultOpts = append(defaultOpts, opts...)

	ctx, cancel := context.WithTimeout(context.Background(), 10*time.Second)
	defer cancel()

	conn, err := grpc.DialContext(ctx, target, defaultOpts...)
	if err != nil {
		return nil, err
	}

	return &Client{
		conn:    conn,
		client:  pb.NewNotifyServiceClient(conn),
		timeout: 30 * time.Second,
	}, nil
}

// Close closes the gRPC connection
func (c *Client) Close() error {
	return c.conn.Close()
}

// SetTimeout sets the default timeout for requests
func (c *Client) SetTimeout(timeout time.Duration) {
	c.timeout = timeout
}

// withMetadata adds correlation ID and other metadata to the context
func withMetadata(ctx context.Context, correlationID string) context.Context {
	md := metadata.Pairs("x-correlation-id", correlationID)
	return metadata.NewOutgoingContext(ctx, md)
}

// SendEmail sends an email via gRPC
func (c *Client) SendEmail(ctx context.Context, req *pb.SendEmailRequest, correlationID string) (*pb.SendEmailResponse, error) {
	ctx, cancel := context.WithTimeout(withMetadata(ctx, correlationID), c.timeout)
	defer cancel()

	log.Printf("[gRPC Client] Sending email to %s", req.To)
	res, err := c.client.SendEmail(ctx, req)
	if err != nil {
		log.Printf("[gRPC Client] SendEmail failed: %v", err)
		return nil, err
	}

	log.Printf("[gRPC Client] SendEmail success: %s", res.Message)
	return res, nil
}

// SendSMS sends an SMS via gRPC
func (c *Client) SendSMS(ctx context.Context, req *pb.SendSMSRequest, correlationID string) (*pb.SendSMSResponse, error) {
	ctx, cancel := context.WithTimeout(withMetadata(ctx, correlationID), c.timeout)
	defer cancel()

	log.Printf("[gRPC Client] Sending SMS to %s", req.To)
	res, err := c.client.SendSMS(ctx, req)
	if err != nil {
		log.Printf("[gRPC Client] SendSMS failed: %v", err)
		return nil, err
	}

	log.Printf("[gRPC Client] SendSMS success: %s", res.Message)
	return res, nil
}

// SendNotification sends a generic notification via gRPC
func (c *Client) SendNotification(ctx context.Context, req *pb.SendNotificationRequest, correlationID string) (*pb.SendNotificationResponse, error) {
	ctx, cancel := context.WithTimeout(withMetadata(ctx, correlationID), c.timeout)
	defer cancel()

	log.Printf("[gRPC Client] Sending notification to user %d", req.UserId)
	res, err := c.client.SendNotification(ctx, req)
	if err != nil {
		log.Printf("[gRPC Client] SendNotification failed: %v", err)
		return nil, err
	}

	log.Printf("[gRPC Client] SendNotification success: email=%v, sms=%v", res.EmailSent, res.SmsSent)
	return res, nil
}

// BroadcastNotification sends notifications to multiple users
func (c *Client) BroadcastNotification(ctx context.Context, req *pb.BroadcastNotificationRequest, correlationID string) (*pb.BroadcastNotificationResponse, error) {
	ctx, cancel := context.WithTimeout(withMetadata(ctx, correlationID), c.timeout)
	defer cancel()

	log.Printf("[gRPC Client] Broadcasting notification to %d recipients", len(req.Recipients))
	res, err := c.client.BroadcastNotification(ctx, req)
	if err != nil {
		log.Printf("[gRPC Client] BroadcastNotification failed: %v", err)
		return nil, err
	}

	log.Printf("[gRPC Client] BroadcastNotification success: %d/%d delivered", res.SuccessCount, res.TotalRecipients)
	return res, nil
}

// GetTemplate retrieves a notification template
func (c *Client) GetTemplate(ctx context.Context, req *pb.GetTemplateRequest, correlationID string) (*pb.GetTemplateResponse, error) {
	ctx, cancel := context.WithTimeout(withMetadata(ctx, correlationID), c.timeout)
	defer cancel()

	log.Printf("[gRPC Client] Getting template %s", req.Id)
	res, err := c.client.GetTemplate(ctx, req)
	if err != nil {
		log.Printf("[gRPC Client] GetTemplate failed: %v", err)
		return nil, err
	}

	log.Printf("[gRPC Client] GetTemplate success: %s", res.Template.Name)
	return res, nil
}

// ListTemplates lists all notification templates
func (c *Client) ListTemplates(ctx context.Context, req *pb.ListTemplatesRequest, correlationID string) (*pb.ListTemplatesResponse, error) {
	ctx, cancel := context.WithTimeout(withMetadata(ctx, correlationID), c.timeout)
	defer cancel()

	log.Printf("[gRPC Client] Listing templates")
	res, err := c.client.ListTemplates(ctx, req)
	if err != nil {
		log.Printf("[gRPC Client] ListTemplates failed: %v", err)
		return nil, err
	}

	log.Printf("[gRPC Client] ListTemplates success: %d templates", res.Total)
	return res, nil
}

// CreateTemplate creates a new notification template
func (c *Client) CreateTemplate(ctx context.Context, req *pb.CreateTemplateRequest, correlationID string) (*pb.CreateTemplateResponse, error) {
	ctx, cancel := context.WithTimeout(withMetadata(ctx, correlationID), c.timeout)
	defer cancel()

	log.Printf("[gRPC Client] Creating template %s", req.Name)
	res, err := c.client.CreateTemplate(ctx, req)
	if err != nil {
		log.Printf("[gRPC Client] CreateTemplate failed: %v", err)
		return nil, err
	}

	log.Printf("[gRPC Client] CreateTemplate success: id=%s", res.Template.Id)
	return res, nil
}

// UpdateTemplate updates an existing notification template
func (c *Client) UpdateTemplate(ctx context.Context, req *pb.UpdateTemplateRequest, correlationID string) (*pb.UpdateTemplateResponse, error) {
	ctx, cancel := context.WithTimeout(withMetadata(ctx, correlationID), c.timeout)
	defer cancel()

	log.Printf("[gRPC Client] Updating template %s", req.Id)
	res, err := c.client.UpdateTemplate(ctx, req)
	if err != nil {
		log.Printf("[gRPC Client] UpdateTemplate failed: %v", err)
		return nil, err
	}

	log.Printf("[gRPC Client] UpdateTemplate success: id=%s", res.Template.Id)
	return res, nil
}

// DeleteTemplate deletes a notification template
func (c *Client) DeleteTemplate(ctx context.Context, req *pb.DeleteTemplateRequest, correlationID string) (*pb.DeleteTemplateResponse, error) {
	ctx, cancel := context.WithTimeout(withMetadata(ctx, correlationID), c.timeout)
	defer cancel()

	log.Printf("[gRPC Client] Deleting template %s", req.Id)
	res, err := c.client.DeleteTemplate(ctx, req)
	if err != nil {
		log.Printf("[gRPC Client] DeleteTemplate failed: %v", err)
		return nil, err
	}

	log.Printf("[gRPC Client] DeleteTemplate success: id=%s", req.Id)
	return res, nil
}

// HealthCheck checks the health of the Notify service
func (c *Client) HealthCheck(ctx context.Context, correlationID string) (*pb.HealthCheckResponse, error) {
	ctx, cancel := context.WithTimeout(withMetadata(ctx, correlationID), c.timeout)
	defer cancel()

	log.Printf("[gRPC Client] Checking health")
	res, err := c.client.HealthCheck(ctx, &pb.HealthCheckRequest{})
	if err != nil {
		log.Printf("[gRPC Client] HealthCheck failed: %v", err)
		return nil, err
	}

	log.Printf("[gRPC Client] HealthCheck success: status=%s", res.Status)
	return res, nil
}
