package phpclient

import (
	"bytes"
	"context"
	"encoding/json"
	"errors"
	"io"
	"net/http"
	"os"
	"strings"
	"time"

	"google.golang.org/grpc/codes"
	"google.golang.org/grpc/status"
)

var ErrTwoFactorRequired = errors.New("two-factor authentication required")

type Caller struct {
	ID    uint
	Roles []string
}

type Client struct {
	baseURL string
	client  *http.Client
}

func New() *Client {
	base := os.Getenv("IDENTITY_HTTP_URL")
	if base == "" {
		base = "http://identity:9000"
	}
	return &Client{baseURL: strings.TrimSuffix(base, "/"), client: &http.Client{Timeout: 10 * time.Second}}
}

type errorBody struct {
	Message string `json:"message"`
}

func grpcError(resp *http.Response) error {
	body, _ := io.ReadAll(io.LimitReader(resp.Body, 4096))
	message := strings.TrimSpace(errorBodyMessage(body))
	if message == "" {
		message = "identity service request failed"
	}
	switch resp.StatusCode {
	case http.StatusUnauthorized:
		return status.Error(codes.Unauthenticated, message)
	case http.StatusForbidden:
		return status.Error(codes.PermissionDenied, message)
	case http.StatusNotFound:
		return status.Error(codes.NotFound, message)
	case http.StatusUnprocessableEntity:
		return status.Error(codes.InvalidArgument, message)
	case http.StatusTooManyRequests:
		return status.Error(codes.ResourceExhausted, message)
	default:
		return status.Errorf(codes.Internal, "identity service returned %d: %s", resp.StatusCode, message)
	}
}

func errorBodyMessage(body []byte) string {
	var parsed errorBody
	if err := json.Unmarshal(body, &parsed); err == nil {
		return parsed.Message
	}
	return string(body)
}

type meBody struct {
	Data struct {
		ID    uint64   `json:"id"`
		Roles []string `json:"roles"`
	} `json:"data"`
}

func (c *Client) Me(ctx context.Context, token string) (*Caller, error) {
	req, err := http.NewRequestWithContext(ctx, http.MethodGet, c.baseURL+"/api/auth/me", nil)
	if err != nil {
		return nil, status.Error(codes.Internal, "failed to build identity request")
	}
	req.Header.Set("Authorization", "Bearer "+token)
	req.Header.Set("Accept", "application/json")
	resp, err := c.client.Do(req)
	if err != nil {
		return nil, status.Error(codes.Unavailable, "identity service is unavailable")
	}
	defer resp.Body.Close()
	if resp.StatusCode != http.StatusOK {
		return nil, grpcError(resp)
	}
	var body meBody
	if err := json.NewDecoder(resp.Body).Decode(&body); err != nil || body.Data.ID == 0 {
		return nil, status.Error(codes.Internal, "invalid identity response")
	}
	roles := make([]string, 0, len(body.Data.Roles))
	for _, role := range body.Data.Roles {
		roles = append(roles, strings.ToLower(role))
	}
	return &Caller{ID: uint(body.Data.ID), Roles: roles}, nil
}

type loginUser struct {
	ID    uint64   `json:"id"`
	Email string   `json:"email"`
	Name  string   `json:"name"`
	Roles []string `json:"roles"`
}

type loginBody struct {
	AccessToken  string    `json:"access_token"`
	ExpiresIn    int64     `json:"expires_in"`
	TokenType    string    `json:"token_type"`
	Data         loginUser `json:"data"`
	Requires2FA  bool      `json:"requires_2fa"`
	PreAuthToken string    `json:"pre_auth_token"`
	Message      string    `json:"message"`
}

type LoginResult struct {
	AccessToken  string
	RefreshToken string
	ExpiresIn    int64
	TokenType    string
	UserID       uint64
}

func refreshTokenFromCookies(resp *http.Response) string {
	for _, cookie := range resp.Cookies() {
		if cookie.Name == "refresh_token" {
			return cookie.Value
		}
	}
	return ""
}

func (c *Client) Login(ctx context.Context, email, password string) (*LoginResult, error) {
	payload, _ := json.Marshal(map[string]string{"email": email, "password": password})
	req, err := http.NewRequestWithContext(ctx, http.MethodPost, c.baseURL+"/api/auth/login", bytes.NewReader(payload))
	if err != nil {
		return nil, status.Error(codes.Internal, "failed to build identity request")
	}
	req.Header.Set("Content-Type", "application/json")
	req.Header.Set("Accept", "application/json")
	resp, err := c.client.Do(req)
	if err != nil {
		return nil, status.Error(codes.Unavailable, "identity service is unavailable")
	}
	defer resp.Body.Close()
	if resp.StatusCode != http.StatusOK {
		return nil, grpcError(resp)
	}
	var body loginBody
	if err := json.NewDecoder(resp.Body).Decode(&body); err != nil {
		return nil, status.Error(codes.Internal, "invalid identity response")
	}
	if body.Requires2FA {
		return nil, status.Errorf(codes.FailedPrecondition, "%v", ErrTwoFactorRequired)
	}
	if body.AccessToken == "" || body.Data.ID == 0 {
		return nil, status.Error(codes.Internal, "invalid identity response")
	}
	return &LoginResult{
		AccessToken:  body.AccessToken,
		RefreshToken: refreshTokenFromCookies(resp),
		ExpiresIn:    body.ExpiresIn,
		TokenType:    body.TokenType,
		UserID:       body.Data.ID,
	}, nil
}

func (c *Client) Refresh(ctx context.Context, refreshToken string) (*LoginResult, error) {
	if strings.TrimSpace(refreshToken) == "" {
		return nil, status.Error(codes.InvalidArgument, "refresh token is required")
	}
	req, err := http.NewRequestWithContext(ctx, http.MethodPost, c.baseURL+"/api/auth/refresh", nil)
	if err != nil {
		return nil, status.Error(codes.Internal, "failed to build identity request")
	}
	req.AddCookie(&http.Cookie{Name: "refresh_token", Value: refreshToken, Path: "/api/auth/"})
	req.Header.Set("Accept", "application/json")
	resp, err := c.client.Do(req)
	if err != nil {
		return nil, status.Error(codes.Unavailable, "identity service is unavailable")
	}
	defer resp.Body.Close()
	if resp.StatusCode != http.StatusOK {
		return nil, grpcError(resp)
	}
	var body loginBody
	if err := json.NewDecoder(resp.Body).Decode(&body); err != nil || body.AccessToken == "" || body.Data.ID == 0 {
		return nil, status.Error(codes.Internal, "invalid identity response")
	}
	return &LoginResult{
		AccessToken:  body.AccessToken,
		RefreshToken: refreshTokenFromCookies(resp),
		ExpiresIn:    body.ExpiresIn,
		TokenType:    body.TokenType,
		UserID:       body.Data.ID,
	}, nil
}

func (c *Client) Logout(ctx context.Context, accessToken string) error {
	if strings.TrimSpace(accessToken) == "" {
		return status.Error(codes.InvalidArgument, "session token is required")
	}
	req, err := http.NewRequestWithContext(ctx, http.MethodPost, c.baseURL+"/api/auth/logout", nil)
	if err != nil {
		return status.Error(codes.Internal, "failed to build identity request")
	}
	req.Header.Set("Authorization", "Bearer "+accessToken)
	req.Header.Set("Accept", "application/json")
	resp, err := c.client.Do(req)
	if err != nil {
		return status.Error(codes.Unavailable, "identity service is unavailable")
	}
	defer resp.Body.Close()
	if resp.StatusCode != http.StatusOK {
		return grpcError(resp)
	}
	return nil
}
