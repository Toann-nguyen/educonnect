package auth

import (
	"context"
	"encoding/json"
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
		if err := waitForKeys(); err != nil {
			log.Printf("JWKS chưa có keys (%v) — retry 5s", err)
			time.Sleep(5 * time.Second)
			continue
		}
		return
	}
}

// waitForKeys — keyfunc khởi tạo thành công ngay cả khi fetch ban đầu lỗi;
// phải xác nhận endpoint JWKS trả về ít nhất 1 key trước khi serve traffic.
func waitForKeys() error {
	client := &http.Client{Timeout: 10 * time.Second}
	req, err := http.NewRequest(http.MethodGet, jwksURL, nil)
	if err != nil {
		return err
	}
	resp, err := client.Do(req)
	if err != nil {
		return err
	}
	defer resp.Body.Close()
	if resp.StatusCode != http.StatusOK {
		return fmt.Errorf("jwks status %d", resp.StatusCode)
	}
	var body struct {
		Keys []json.RawMessage `json:"keys"`
	}
	if err := json.NewDecoder(resp.Body).Decode(&body); err != nil {
		return err
	}
	if len(body.Keys) == 0 {
		return fmt.Errorf("jwks has no keys")
	}
	return nil
}

// strictClaims — AUTH_STRICT_CLAIMS=true bắt iss/aud/sid đầy đủ (new auth flow).
// Mặc định false để tương thích token legacy (iss=request URL, không aud/sid);
// chữ ký RS256 + exp + tv/ver revoke vẫn luôn được kiểm tra.
func strictClaims() bool {
	return strings.EqualFold(os.Getenv("AUTH_STRICT_CLAIMS"), "true")
}

// verificationKey — chọn key theo kid; token legacy không có kid thì dùng
// key JWKS duy nhất khớp alg (hiện chỉ có 1 RSA key cho RS256).
func verificationKey(token *jwt.Token) (any, error) {
	if kid, _ := token.Header["kid"].(string); kid != "" {
		return jwks.Keyfunc(token)
	}
	keys, err := jwks.Storage().KeyReadAll(context.Background())
	if err != nil {
		return nil, err
	}
	var candidates []any
	for _, key := range keys {
		if string(key.Marshal().ALG) == token.Method.Alg() {
			candidates = append(candidates, key.Key())
		}
	}
	if len(candidates) != 1 {
		return nil, fmt.Errorf("ambiguous JWKS key for kid-less token")
	}
	return candidates[0], nil
}

// verifyToken — parse + verify RS256 + kiểm tra exp/tv/ver (luôn luôn);
// iss/aud/sid chỉ bắt buộc khi AUTH_STRICT_CLAIMS=true.
func verifyToken(tokenStr string) (jwt.MapClaims, error) {
	if jwks == nil {
		return nil, fmt.Errorf("JWKS chưa khởi tạo")
	}
	token, err := jwt.Parse(tokenStr, verificationKey, jwt.WithValidMethods([]string{"RS256"}))
	if err != nil || !token.Valid {
		return nil, fmt.Errorf("invalid token: %w", err)
	}
	claims, ok := token.Claims.(jwt.MapClaims)
	if !ok {
		return nil, fmt.Errorf("invalid claims type")
	}

	if iss, _ := claims["iss"].(string); iss != issuer && strictClaims() {
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
	if _, present := claims["aud"]; !present && !strictClaims() {
		// Token legacy không có aud — bỏ qua khi không ở strict mode.
	} else if !audValid {
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
	if sid, ok := claims["sid"]; (!ok || sid == nil || sid == "") && strictClaims() {
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
		// T5.1 hybrid revoke: jti blacklist + sid revoked + tv stale (Redis)
		if jti, _ := claims["jti"].(string); IsJTIBlacklisted(jti) {
			c.JSON(http.StatusUnauthorized, gin.H{"message": "Token has been revoked (jti blacklisted)"})
			c.Abort()
			return
		}
		if sid, _ := claims["sid"].(string); IsSidRevoked(sid) {
			c.JSON(http.StatusUnauthorized, gin.H{"message": "Token has been revoked (session revoked)"})
			c.Abort()
			return
		}
		if tvRaw, ok := claims["tv"]; ok {
			if sid, _ := claims["sid"].(string); sid == "" {
				_ = sid
			}
			var tvFloat float64
			switch v := tvRaw.(type) {
			case float64:
				tvFloat = v
			case int:
				tvFloat = float64(v)
			case int64:
				tvFloat = float64(v)
			}
			if sub, _ := claims["sub"].(string); sub != "" && IsTVStale(sub, tvFloat) {
				c.JSON(http.StatusUnauthorized, gin.H{"message": "Token has been revoked (token version stale)"})
				c.Abort()
				return
			}
			// string sub fallback (numeric)
			if subF, ok := claims["sub"].(float64); ok {
				if IsTVStale(fmt.Sprint(int(subF)), tvFloat) {
					c.JSON(http.StatusUnauthorized, gin.H{"message": "Token has been revoked (token version stale)"})
					c.Abort()
					return
				}
			}
		} else if verRaw, ok := claims["ver"]; ok {
			var tvFloat float64
			switch v := verRaw.(type) {
			case float64:
				tvFloat = v
			case int:
				tvFloat = float64(v)
			}
			if sub, _ := claims["sub"].(string); sub != "" && IsTVStale(sub, tvFloat) {
				c.JSON(http.StatusUnauthorized, gin.H{"message": "Token has been revoked (token version stale)"})
				c.Abort()
				return
			}
		}
		c.Set("claims", claims)
		c.Set("user_id", claims["sub"])
		c.Set("sid", claims["sid"])
		// T6.1: near-realtime revoke qua auth.events (user.disabled/password_changed).
		if IsUserRevoked(fmt.Sprint(claims["sub"])) {
			c.JSON(http.StatusForbidden, gin.H{"message": "Account revoked — please re-login"})
			c.Abort()
			return
		}
		// T3.2: expose roles để RBAC/ownership không phải parse lại claims
		if roles, ok := claims["roles"]; ok {
			switch v := roles.(type) {
			case []interface{}:
				rs := make([]string, 0, len(v))
				for _, e := range v {
					if s, ok := e.(string); ok {
						rs = append(rs, s)
					}
				}
				c.Set("roles", rs)
			case []string:
				c.Set("roles", v)
			case string:
				c.Set("roles", []string{v})
			}
		}
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
		// T5.1 hybrid: jti/sid/tv
		if jti, _ := claims["jti"].(string); IsJTIBlacklisted(jti) {
			http.Error(w, `{"message":"Token has been revoked (jti blacklisted)"}`, http.StatusUnauthorized)
			return
		}
		if sid, _ := claims["sid"].(string); IsSidRevoked(sid) {
			http.Error(w, `{"message":"Token has been revoked (session revoked)"}`, http.StatusUnauthorized)
			return
		}
		if tvRaw, ok := claims["tv"]; ok {
			var tvFloat float64
			switch v := tvRaw.(type) {
			case float64:
				tvFloat = v
			case int:
				tvFloat = float64(v)
			case int64:
				tvFloat = float64(v)
			}
			if sub, _ := claims["sub"].(string); sub != "" && IsTVStale(sub, tvFloat) {
				http.Error(w, `{"message":"Token has been revoked (token version stale)"}`, http.StatusUnauthorized)
				return
			}
		} else if verRaw, ok := claims["ver"]; ok {
			var tvFloat float64
			switch v := verRaw.(type) {
			case float64:
				tvFloat = v
			case int:
				tvFloat = float64(v)
			}
			if sub, _ := claims["sub"].(string); sub != "" && IsTVStale(sub, tvFloat) {
				http.Error(w, `{"message":"Token has been revoked (token version stale)"}`, http.StatusUnauthorized)
				return
			}
		}
		ctx := context.WithValue(r.Context(), "claims", claims)
		ctx = context.WithValue(ctx, "user_id", claims["sub"])
		ctx = context.WithValue(ctx, "sid", claims["sid"])
		// T6.1: near-realtime revoke qua auth.events.
		if IsUserRevoked(fmt.Sprint(claims["sub"])) {
			http.Error(w, `{"message":"Account revoked — please re-login"}`, http.StatusForbidden)
			return
		}
		if roles, ok := claims["roles"]; ok {
			ctx = context.WithValue(ctx, "roles", roles)
		}
		next.ServeHTTP(w, r.WithContext(ctx))
	})
}

var _ = chi.NewRouter

// CloseJWKS — placeholder: keyfunc v3 dừng refresh khi context bị cancel (main cancel).
func CloseJWKS() {}
