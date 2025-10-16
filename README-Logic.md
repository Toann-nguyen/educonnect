### Hệ thống RBAC (Role-Based Access Control)
```Tổng quan
Hệ thống quản lý phân quyền dựa trên vai trò (RBAC) cho phép quản lý người dùng, vai trò (roles) và quyền hạn (permissions) một cách linh hoạt.
Kiến trúc hệ thống
1. Models
```
```
Role: Vai trò của người dùng (admin, teacher, student, etc.)
Permission: Quyền hạn cụ thể (manage_users, view_reports, etc.)
User: Người dùng hệ thống
```
```
2. Relationships

User many-to-many Role (một user có nhiều roles)
Role many-to-many Permission (một role có nhiều permissions)
User many-to-many Permission (user có thể có quyền trực tiếp, không qua role)
```