package auth

import (
	"github.com/gin-gonic/gin"

	"educonnect/notify/internal/template"
)

// ── Ownership helpers — 3rd layer cho Notify templates ───────────────────
// Principal/admin → all.
// Homeroom/Teacher → own (CreatedBy == user_id) cho write; read thì allowed nếu có view permission.
// Student/Parent → view only (ownership = own not needed for list, but write denied).

func IsAllAccessNotify(c *gin.Context) bool {
	return HasRoleGin(c, RolePrincipal, RoleAdmin)
}

func IsTeacherScopedNotify(c *gin.Context) bool {
	return HasRoleGin(c, RoleHomeroom, RoleTeacher) && !IsAllAccessNotify(c)
}

func IsStudentNotify(c *gin.Context) bool {
	return HasRoleGin(c, RoleStudent, RoleParent) && !HasRoleGin(c, RolePrincipal, RoleAdmin, RoleHomeroom, RoleTeacher)
}

// CanAccessTemplate — kiểm tra quyền truy cập 1 template.
// - Principal/all: true
// - Teacher/Homeroom: own (CreatedBy == uid) cho read/write — nếu template.CreatedBy==0 (legacy) thì allow read.
// - Student/Parent: chỉ read (write sẽ bị chặn ở permission layer), own check vẫn pass cho read.
func CanAccessTemplate(c *gin.Context, t *template.Template) bool {
	if IsAllAccessNotify(c) {
		return true
	}
	uid := GetUserID(c)
	if t == nil {
		return false
	}
	if IsTeacherScopedNotify(c) {
		// Legacy template chưa có CreatedBy → coi như public read, nhưng write chỉ owner
		if t.CreatedBy == 0 {
			return true
		}
		return t.CreatedBy == uid
	}
	// Student/Parent: cho phép read (ownership không restrict list), write đã bị RequirePermissions chặn
	if IsStudentNotify(c) {
		return true
	}
	// Accountant/librarian etc. — read allowed nếu có view permission, ownership pass
	return true
}

// CanModifyTemplate — write ownership: chỉ principal/all hoặc owner.
func CanModifyTemplate(c *gin.Context, t *template.Template) bool {
	if IsAllAccessNotify(c) {
		return true
	}
	uid := GetUserID(c)
	if t == nil {
		// create: bất kỳ ai có manage permission đều được tạo (CreatedBy sẽ set)
		return HasPermissionGin(c, PermManageNotify)
	}
	if t.CreatedBy == 0 {
		// legacy → allow homeroom/teacher with manage perm
		return HasPermissionGin(c, PermManageNotify)
	}
	return t.CreatedBy == uid
}
