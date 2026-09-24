package authclient

import (
	"context"
	"encoding/json"
	"fmt"
	"net/http"
	"os"
	"strings"
	"time"

	"google.golang.org/grpc/codes"
	"google.golang.org/grpc/metadata"
	"google.golang.org/grpc/status"
)

type Caller struct {
	ID    uint
	Roles []string
}

type meResponse struct {
	Data struct {
		ID    uint64   `json:"id"`
		Roles []string `json:"roles"`
	} `json:"data"`
}

func baseURL() string {
	if base := os.Getenv("IDENTITY_HTTP_URL"); base != "" {
		return strings.TrimSuffix(base, "/")
	}
	return "http://identity:9000"
}

func tokenFromMetadata(ctx context.Context) (string, error) {
	md, ok := metadata.FromIncomingContext(ctx)
	if !ok {
		return "", status.Error(codes.Unauthenticated, "authorization metadata is required")
	}
	values := md.Get("authorization")
	if len(values) == 0 || strings.TrimSpace(values[0]) == "" {
		return "", status.Error(codes.Unauthenticated, "bearer token is required")
	}
	token := strings.TrimSpace(values[0])
	token = strings.TrimPrefix(token, "Bearer ")
	if strings.TrimSpace(token) == "" {
		return "", status.Error(codes.Unauthenticated, "bearer token is required")
	}
	return token, nil
}

func Authenticate(ctx context.Context) (*Caller, error) {
	token, err := tokenFromMetadata(ctx)
	if err != nil {
		return nil, err
	}
	requestCtx, cancel := context.WithTimeout(ctx, 5*time.Second)
	defer cancel()
	req, err := http.NewRequestWithContext(requestCtx, http.MethodGet, baseURL()+"/api/auth/me", nil)
	if err != nil {
		return nil, status.Error(codes.Internal, "failed to build identity request")
	}
	req.Header.Set("Authorization", "Bearer "+token)
	req.Header.Set("Accept", "application/json")
	resp, err := http.DefaultClient.Do(req)
	if err != nil {
		return nil, status.Error(codes.Unavailable, "identity service is unavailable")
	}
	defer resp.Body.Close()
	if resp.StatusCode == http.StatusUnauthorized || resp.StatusCode == http.StatusForbidden {
		return nil, status.Error(codes.Unauthenticated, "invalid or expired token")
	}
	if resp.StatusCode != http.StatusOK {
		return nil, status.Errorf(codes.Internal, "identity service returned %d", resp.StatusCode)
	}
	var body meResponse
	if err := json.NewDecoder(resp.Body).Decode(&body); err != nil {
		return nil, status.Error(codes.Internal, "invalid identity response")
	}
	if body.Data.ID == 0 {
		return nil, status.Error(codes.Unauthenticated, "invalid identity response")
	}
	roles := make([]string, 0, len(body.Data.Roles))
	for _, role := range body.Data.Roles {
		roles = append(roles, strings.ToLower(role))
	}
	return &Caller{ID: uint(body.Data.ID), Roles: roles}, nil
}

func (c *Caller) HasRole(roles ...string) bool {
	set := make(map[string]bool, len(c.Roles))
	for _, role := range c.Roles {
		set[role] = true
	}
	for _, role := range roles {
		if set[strings.ToLower(role)] {
			return true
		}
	}
	return false
}

func (c *Caller) IsStaff() bool {
	return c.HasRole("admin", "principal", "teacher", "homeroom")
}

func (c *Caller) IsWriter() bool {
	return c.HasRole("admin", "principal")
}

func correlationID(ctx context.Context) string {
	if md, ok := metadata.FromIncomingContext(ctx); ok {
		if values := md.Get("x-correlation-id"); len(values) > 0 {
			return values[0]
		}
	}
	return fmt.Sprintf("%d", time.Now().UnixNano())
}
