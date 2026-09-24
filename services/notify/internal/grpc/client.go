package grpc

import (
	"context"
	"log"
	"time"

	pb "educonnect/internal/pkg/proto/notify"
	"google.golang.org/grpc"
	"google.golang.org/grpc/credentials/insecure"
	healthpb "google.golang.org/grpc/health/grpc_health_v1"
	"google.golang.org/grpc/metadata"
)

type Client struct {
	conn    *grpc.ClientConn
	client  pb.NotificationServiceClient
	events  pb.NotificationEventServiceClient
	health  healthpb.HealthClient
	timeout time.Duration
}

func NewClient(target string, opts ...grpc.DialOption) (*Client, error) {
	defaultOpts := []grpc.DialOption{grpc.WithTransportCredentials(insecure.NewCredentials())}
	defaultOpts = append(defaultOpts, opts...)
	conn, err := grpc.NewClient(target, defaultOpts...)
	if err != nil {
		return nil, err
	}
	return &Client{
		conn:    conn,
		client:  pb.NewNotificationServiceClient(conn),
		events:  pb.NewNotificationEventServiceClient(conn),
		health:  healthpb.NewHealthClient(conn),
		timeout: 30 * time.Second,
	}, nil
}

func (c *Client) Close() error {
	return c.conn.Close()
}

func (c *Client) SetTimeout(timeout time.Duration) {
	c.timeout = timeout
}

func (c *Client) withMetadata(ctx context.Context, correlationID string) (context.Context, context.CancelFunc) {
	ctx = metadata.AppendToOutgoingContext(ctx, "x-correlation-id", correlationID)
	return context.WithTimeout(ctx, c.timeout)
}

func (c *Client) SendNotification(ctx context.Context, req *pb.SendNotificationRequest, correlationID string) (*pb.SendNotificationResponse, error) {
	ctx, cancel := c.withMetadata(ctx, correlationID)
	defer cancel()
	log.Printf("[gRPC Client] Sending notification to user %s", req.GetUserId())
	return c.client.SendNotification(ctx, req)
}

func (c *Client) SendEmail(ctx context.Context, to, title, body, correlationID string) (*pb.SendNotificationResponse, error) {
	return c.SendNotification(ctx, &pb.SendNotificationRequest{
		Email:   to,
		Title:   title,
		Message: body,
		Type:    pb.NotificationType_NOTIFICATION_TYPE_EMAIL,
	}, correlationID)
}

func (c *Client) SendSMS(ctx context.Context, phone, message, correlationID string) (*pb.SendNotificationResponse, error) {
	return c.SendNotification(ctx, &pb.SendNotificationRequest{
		Phone:   phone,
		Message: message,
		Type:    pb.NotificationType_NOTIFICATION_TYPE_SMS,
	}, correlationID)
}

func (c *Client) SendTemplateNotification(ctx context.Context, req *pb.SendTemplateNotificationRequest, correlationID string) (*pb.SendNotificationResponse, error) {
	ctx, cancel := c.withMetadata(ctx, correlationID)
	defer cancel()
	return c.client.SendTemplateNotification(ctx, req)
}

func (c *Client) SendBulkNotifications(ctx context.Context, req *pb.SendBulkNotificationsRequest, correlationID string) (*pb.SendBulkNotificationsResponse, error) {
	ctx, cancel := c.withMetadata(ctx, correlationID)
	defer cancel()
	return c.client.SendBulkNotifications(ctx, req)
}

func (c *Client) GetNotification(ctx context.Context, req *pb.GetNotificationRequest, correlationID string) (*pb.GetNotificationResponse, error) {
	ctx, cancel := c.withMetadata(ctx, correlationID)
	defer cancel()
	return c.client.GetNotification(ctx, req)
}

func (c *Client) GetNotificationsByUser(ctx context.Context, req *pb.GetNotificationsByUserRequest, correlationID string) (*pb.GetNotificationsResponse, error) {
	ctx, cancel := c.withMetadata(ctx, correlationID)
	defer cancel()
	return c.client.GetNotificationsByUser(ctx, req)
}

func (c *Client) MarkAsRead(ctx context.Context, req *pb.MarkAsReadRequest, correlationID string) (*pb.MarkAsReadResponse, error) {
	ctx, cancel := c.withMetadata(ctx, correlationID)
	defer cancel()
	return c.client.MarkAsRead(ctx, req)
}

func (c *Client) MarkAllAsRead(ctx context.Context, req *pb.MarkAllAsReadRequest, correlationID string) (*pb.MarkAsReadResponse, error) {
	ctx, cancel := c.withMetadata(ctx, correlationID)
	defer cancel()
	return c.client.MarkAllAsRead(ctx, req)
}

func (c *Client) ProcessInvoicePaymentEvent(ctx context.Context, req *pb.ProcessInvoicePaymentEventRequest, correlationID string) (*pb.ProcessInvoicePaymentEventResponse, error) {
	ctx, cancel := c.withMetadata(ctx, correlationID)
	defer cancel()
	return c.events.ProcessInvoicePaymentEvent(ctx, req)
}

func (c *Client) HealthCheck(ctx context.Context, correlationID string) (*healthpb.HealthCheckResponse, error) {
	ctx, cancel := c.withMetadata(ctx, correlationID)
	defer cancel()
	return c.health.Check(ctx, &healthpb.HealthCheckRequest{})
}
