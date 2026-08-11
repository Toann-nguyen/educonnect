package auth

import (
	"crypto/rsa"
	"fmt"
	"net/http"
	"os"

	"github.com/gin-gonic/gin"
	"github.com/golang-jwt/jwt/v5"
)

var jwtPublicKey *rsa.PublicKey

// LoadJWTPublicKey — load RS256 public key của Identity (1 lần lúc start).
func LoadJWTPublicKey() error {
	pemPath := os.Getenv("JWT_PUBLIC_KEY_PATH")
	if pemPath == "" {
		pemPath = "/certs/jwt-rsa-4096-public.pem"
	}
	pemBytes, err := os.ReadFile(pemPath)
	if err != nil {
		return fmt.Errorf("read public key %s: %w", pemPath, err)
	}
	jwtPublicKey, err = jwt.ParseRSAPublicKeyFromPEM(pemBytes)
	if err != nil {
		return fmt.Errorf("parse public key: %w", err)
	}
	return nil
}

// JWT — gin middleware verify Authorization: Bearer <jwt> (RS256).
func JWT() gin.HandlerFunc {
	return func(c *gin.Context) {
		tokenStr := c.GetHeader("Authorization")
		if tokenStr == "" {
			c.JSON(http.StatusUnauthorized, gin.H{"message": "Authorization header required"})
			c.Abort()
			return
		}
		if len(tokenStr) > 7 && tokenStr[:7] == "Bearer " {
			tokenStr = tokenStr[7:]
		}

		token, err := jwt.Parse(tokenStr, func(t *jwt.Token) (interface{}, error) {
			if _, ok := t.Method.(*jwt.SigningMethodRSA); !ok {
				return nil, fmt.Errorf("unexpected signing method: %v", t.Header["alg"])
			}
			return jwtPublicKey, nil
		})
		if err != nil || !token.Valid {
			c.JSON(http.StatusUnauthorized, gin.H{"message": "Invalid or expired token"})
			c.Abort()
			return
		}

		if claims, ok := token.Claims.(jwt.MapClaims); ok {
			c.Set("user_id", claims["sub"])
		}
		c.Next()
	}
}
