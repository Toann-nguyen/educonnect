package auth

import (
	"net/http"
	"strings"

	"github.com/gin-gonic/gin"
	"github.com/golang-jwt/jwt/v5"
)

// ── Role constants (mirror RoleEnum) ─────────────────────────────────────

const (
	RoleAdmin      = "admin"
	RolePrincipal  = "principal"
	RoleHomeroom   = "homeroom"
	RoleTeacher    = "teacher"
	RoleStudent    = "student"
	RoleParent     = "parent"
	RoleAccountant = "accountant"
	RoleLibrarian  = "librarian"
	RoleRedScarf   = "red_scarf"
)

// ── Permission constants (mirror PermissionEnum + finance/notify shared) ─────

const (
	PermManageFinances = "manage_finances"
	PermViewInvoices   = "view_invoices"
	PermManageInvoices = "manage_invoices"
	PermManagePayments = "manage_payments"
	PermViewUsers      = "view_users"
	PermManageUsers    = "manage_users"
	// Notify: reuse manage_events/view_events cho templates
	PermViewNotify   = "view_events"
	PermManageNotify = "manage_events"
)

// rolePermissions — §10.2 matrix + notify/finance extensions.
// principal/admin = all ("*"). Others map to enumerated perms.
var rolePermissions = map[string][]string{
	RolePrincipal:  {"*"},
	RoleAdmin:      {"*"},
	RoleAccountant: {PermManageFinances, PermViewInvoices, PermManageInvoices, PermManagePayments, PermViewUsers, PermManageUsers},
	RoleHomeroom:   {PermViewInvoices, PermManageInvoices, PermViewUsers, PermViewNotify, PermManageNotify},
	RoleTeacher:    {PermViewInvoices, PermViewUsers, PermViewNotify},
	RoleStudent:    {PermViewInvoices, PermViewNotify},
	RoleParent:     {PermViewInvoices, PermViewNotify},
	RoleLibrarian:  {PermViewUsers},
	RoleRedScarf:   {PermViewNotify},
}

// ── Claim helpers ─────────────────────────────────────────────────────────

func GetRoles(c *gin.Context) []string {
	if v, ok := c.Get("roles"); ok {
		if rs, ok := v.([]string); ok {
			return rs
		}
	}
	if v, ok := c.Get("claims"); ok {
		if claims, ok := v.(jwt.MapClaims); ok {
			return rolesFromClaims(claims)
		}
	}
	// fallback: try user_id claim roles
	return nil
}

func GetUserID(c *gin.Context) uint {
	if v, ok := c.Get("user_id"); ok {
		switch x := v.(type) {
		case string:
			var uid uint
			for _, ch := range x {
				if ch >= '0' && ch <= '9' {
					uid = uid*10 + uint(ch-'0')
				}
			}
			return uid
		case float64:
			return uint(x)
		case int:
			return uint(x)
		case int64:
			return uint(x)
		case uint:
			return x
		case uint64:
			return uint(x)
		}
	}
	return 0
}

func rolesFromClaims(claims jwt.MapClaims) []string {
	raw, ok := claims["roles"]
	if !ok || raw == nil {
		return nil
	}
	switch v := raw.(type) {
	case []string:
		return v
	case []interface{}:
		out := make([]string, 0, len(v))
		for _, e := range v {
			if s, ok := e.(string); ok {
				out = append(out, s)
			}
		}
		return out
	case string:
		// comma or single
		if strings.Contains(v, ",") {
			parts := strings.Split(v, ",")
			for i := range parts {
				parts[i] = strings.TrimSpace(parts[i])
			}
			return parts
		}
		return []string{v}
	}
	return nil
}

func HasRole(claims jwt.MapClaims, roles ...string) bool {
	have := rolesFromClaims(claims)
	m := make(map[string]bool, len(have))
	for _, r := range have {
		m[strings.ToLower(r)] = true
	}
	for _, want := range roles {
		if m[strings.ToLower(want)] {
			return true
		}
	}
	return false
}

func HasRoleGin(c *gin.Context, roles ...string) bool {
	rs := GetRoles(c)
	m := make(map[string]bool, len(rs))
	for _, r := range rs {
		m[strings.ToLower(r)] = true
	}
	for _, want := range roles {
		if m[strings.ToLower(want)] {
			return true
		}
	}
	return false
}

func IsPrivileged(c *gin.Context) bool {
	return HasRoleGin(c, RolePrincipal, RoleAdmin)
}

func HasPermission(claims jwt.MapClaims, perms ...string) bool {
	// direct permissions claim if present
	if raw, ok := claims["permissions"]; ok && raw != nil {
		var havePerms []string
		switch v := raw.(type) {
		case []string:
			havePerms = v
		case []interface{}:
			for _, e := range v {
				if s, ok := e.(string); ok {
					havePerms = append(havePerms, s)
				}
			}
		}
		m := make(map[string]bool, len(havePerms))
		for _, p := range havePerms {
			m[strings.ToLower(p)] = true
		}
		for _, want := range perms {
			if m[strings.ToLower(want)] {
				return true
			}
		}
	}
	// fallback: derive from roles
	haveRoles := rolesFromClaims(claims)
	for _, r := range haveRoles {
		permsForRole := rolePermissions[strings.ToLower(r)]
		for _, pr := range permsForRole {
			if pr == "*" {
				return true
			}
		}
		m := make(map[string]bool, len(permsForRole))
		for _, p := range permsForRole {
			m[strings.ToLower(p)] = true
		}
		for _, want := range perms {
			if m[strings.ToLower(want)] {
				return true
			}
		}
	}
	return false
}

func HasPermissionGin(c *gin.Context, perms ...string) bool {
	if v, ok := c.Get("claims"); ok {
		if claims, ok := v.(jwt.MapClaims); ok {
			return HasPermission(claims, perms...)
		}
	}
	// fallback via roles only
	rs := GetRoles(c)
	for _, r := range rs {
		permsForRole := rolePermissions[strings.ToLower(r)]
		for _, pr := range permsForRole {
			if pr == "*" {
				return true
			}
		}
		m := make(map[string]bool, len(permsForRole))
		for _, p := range permsForRole {
			m[strings.ToLower(p)] = true
		}
		for _, want := range perms {
			if m[strings.ToLower(want)] {
				return true
			}
		}
	}
	return false
}

// ── Gin middlewares — 3 lớp: role → permission (ownership handled in handler/ownership.go) ─

func RequireRoles(roles ...string) gin.HandlerFunc {
	return func(c *gin.Context) {
		if len(roles) == 0 {
			c.Next()
			return
		}
		if HasRoleGin(c, roles...) {
			c.Next()
			return
		}
		c.AbortWithStatusJSON(http.StatusForbidden, gin.H{
			"message":        "Forbidden: insufficient role",
			"required_roles": roles,
		})
	}
}

func RequirePermissions(perms ...string) gin.HandlerFunc {
	return func(c *gin.Context) {
		if len(perms) == 0 {
			c.Next()
			return
		}
		if HasPermissionGin(c, perms...) {
			c.Next()
			return
		}
		c.AbortWithStatusJSON(http.StatusForbidden, gin.H{
			"message":             "Forbidden: insufficient permission",
			"required_permissions": perms,
		})
	}
}

// Require — combined role + permission check (AND).
// Either roles or perms can be empty to skip that layer.
func Require(roles []string, perms []string) gin.HandlerFunc {
	return func(c *gin.Context) {
		if len(roles) > 0 && !HasRoleGin(c, roles...) {
			c.AbortWithStatusJSON(http.StatusForbidden, gin.H{"message": "Forbidden: insufficient role", "required_roles": roles})
			return
		}
		if len(perms) > 0 && !HasPermissionGin(c, perms...) {
			c.AbortWithStatusJSON(http.StatusForbidden, gin.H{"message": "Forbidden: insufficient permission", "required_permissions": perms})
			return
		}
		c.Next()
	}
}

// Chi variants (for services using chi)

func ChiRequireRoles(roles ...string) func(http.Handler) http.Handler {
	return func(next http.Handler) http.Handler {
		return http.HandlerFunc(func(w http.ResponseWriter, r *http.Request) {
			claims, _ := r.Context().Value("claims").(jwt.MapClaims)
			if claims == nil || !HasRole(claims, roles...) {
				http.Error(w, `{"message":"Forbidden: insufficient role"}`, http.StatusForbidden)
				return
			}
			next.ServeHTTP(w, r)
		})
	}
}

func ChiRequirePermissions(perms ...string) func(http.Handler) http.Handler {
	return func(next http.Handler) http.Handler {
		return http.HandlerFunc(func(w http.ResponseWriter, r *http.Request) {
			claims, _ := r.Context().Value("claims").(jwt.MapClaims)
			if claims == nil || !HasPermission(claims, perms...) {
				http.Error(w, `{"message":"Forbidden: insufficient permission"}`, http.StatusForbidden)
				return
			}
			next.ServeHTTP(w, r)
		})
	}
}
