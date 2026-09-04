package auth

import (
	"context"
	"fmt"
	"log"
	"net/http"
	"os"
	"strings"
	"time"

	"github.com/MicahParks/keyfunc/v3"
	"github.com/gin-gonic/gin"
	"github.com/go-chi/chi/v5"
	"github.com/golang-jwt/jwt/v5"
)

var (
	jwks     keyfunc.Keyfunc
	issuer   string
	audience string
	jwksURL  string
)

// InitJWKS — khởi tạo JWKS cache với MicahParks/keyfunc.
// Đọc AUTH_JWKS_URL, AUTH_ISSUER, AUTH_AUDIENCE từ env, có default cho docker.
func InitJWKS(ctx context.Context) error {
	jwksURL = os.Getenv("AUTH_JWKS_URL")
	if jwksURL == "" {
		jwksURL = os.Getenv("JWKS_URL")
	}
	if jwksURL == "" {
		jwksURL = "http://gateway/.well-known/jwks.json"
	}
	issuer = os.Getenv("AUTH_ISSUER")
	if issuer == "" {
		issuer = "http://localhost"
	}
	audience = os.Getenv("AUTH_AUDIENCE")
	if audience == "" {
		audience = "educonnect-api"
	}

	log.Printf("JWKS: url=%s iss=%s aud=%s", jwksURL, issuer, audience)

	k, err := keyfunc.NewDefaultOverrideCtx(ctx, []string{jwksURL}, keyfunc.Override{
		RefreshInterval: 15 * time.Minute,
		HTTPTimeout:     10 * time.Second,
	})
	if err != nil {
		return fmt.Errorf("init JWKS %s: %w", jwksURL, err)
	}
	jwks = k
	log.Printf("JWKS cache initialized (%s)", jwksURL)
	return nil
}

// MustInitJWKS — helper cho main: retry cho tới khi JWKS sẵn sàng (identity/gateway chưa lên).
func MustInitJWKS(ctx context.Context) {
	for {
		if err := InitJWKS(ctx); err != nil {
			log.Printf("JWKS chưa sẵn sàng (%v) — retry 5s", err)
			time.Sleep(5 * time.Second)
			continue
		}
		return
	}
}

// verifyToken — parse + verify RS256 + kiểm tra iss/aud/exp/kid/tv/sid.
func verifyToken(tokenStr string) (jwt.MapClaims, error) {
	if jwks == nil {
		return nil, fmt.Errorf("JWKS chưa khởi tạo")
	}
	token, err := jwt.Parse(tokenStr, jwks.Keyfunc, jwt.WithValidMethods([]string{"RS256"}))
	if err != nil || !token.Valid {
		return nil, fmt.Errorf("invalid token: %w", err)
	}
	claims, ok := token.Claims.(jwt.MapClaims)
	if !ok {
		return nil, fmt.Errorf("invalid claims type")
	}

	if kid, _ := token.Header["kid"].(string); kid == "" {
		return nil, fmt.Errorf("kid missing")
	}
	if iss, _ := claims["iss"].(string); iss != issuer {
		return nil, fmt.Errorf("invalid iss: %s", claims["iss"])
	}
	audValid := false
	switch v := claims["aud"].(type) {
	case string:
		audValid = v == audience
	case []interface{}:
		for _, a := range v {
			if s, ok := a.(string); ok && s == audience {
				audValid = true
				break
			}
		}
	case []string:
		for _, s := range v {
			if s == audience {
				audValid = true
				break
			}
		}
	}
	if !audValid {
		return nil, fmt.Errorf("invalid aud: %v", claims["aud"])
	}
	if exp, ok := claims["exp"]; !ok || exp == nil {
		return nil, fmt.Errorf("exp missing")
	}
	if tv, ok := claims["tv"]; !ok || tv == nil {
		if ver, ok2 := claims["ver"]; !ok2 || ver == nil {
			return nil, fmt.Errorf("tv/ver missing")
		}
	}
	if sid, ok := claims["sid"]; !ok || sid == nil || sid == "" {
		return nil, fmt.Errorf("sid missing")
	}
	return claims, nil
}

// JWT — gin middleware verify Authorization: Bearer <jwt> (RS256 + JWKS cache).
func JWT() gin.HandlerFunc {
	return func(c *gin.Context) {
		tokenStr := c.GetHeader("Authorization")
		if tokenStr == "" {
			c.JSON(http.StatusUnauthorized, gin.H{"message": "Authorization header required"})
			c.Abort()
			return
		}
		if strings.HasPrefix(tokenStr, "Bearer ") {
			tokenStr = strings.TrimPrefix(tokenStr, "Bearer ")
		}
		claims, err := verifyToken(tokenStr)
		if err != nil {
			c.JSON(http.StatusUnauthorized, gin.H{"message": "Invalid or expired token", "detail": err.Error()})
			c.Abort()
			return
		}
		c.Set("claims", claims)
		c.Set("user_id", claims["sub"])
		c.Set("sid", claims["sid"])
		if v, ok := claims["tv"]; ok {
			c.Set("tv", v)
		} else {
			c.Set("tv", claims["ver"])
		}
		c.Next()
	}
}

// ChiJWT — chi v5 middleware tương đương (dùng cho service nào chọn chi router).
func ChiJWT(next http.Handler) http.Handler {
	return http.HandlerFunc(func(w http.ResponseWriter, r *http.Request) {
		tokenStr := r.Header.Get("Authorization")
		if tokenStr == "" {
			http.Error(w, `{"message":"Authorization header required"}`, http.StatusUnauthorized)
			return
		}
		if strings.HasPrefix(tokenStr, "Bearer ") {
			tokenStr = strings.TrimPrefix(tokenStr, "Bearer ")
		}
		claims, err := verifyToken(tokenStr)
		if err != nil {
			http.Error(w, fmt.Sprintf(`{"message":"Invalid or expired token","detail":%q}`, err.Error()), http.StatusUnauthorized)
			return
		}
		ctx := context.WithValue(r.Context(), "claims", claims)
		ctx = context.WithValue(ctx, "user_id", claims["sub"])
		ctx = context.WithValue(ctx, "sid", claims["sid"])
		next.ServeHTTP(w, r.WithContext(ctx))
	})
}

var _ = chi.NewRouter

// CloseJWKS — placeholder: keyfunc v3 dừng refresh khi context bị cancel (main cancel).
func CloseJWKS() {}
