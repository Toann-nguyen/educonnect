# Identity — Business Domain

## Overview

The Identity domain manages all authentication, authorization, and user-related operations. It is the foundation of the EduConnect system — every other domain depends on it for user identity and access control.

**Database connection**: `identity` (separate from school and finance)

## Business Capabilities

### 1. User Registration & Onboarding

**Flow**: User submits email + password → system creates account → sends verification email → user verifies email → account is active.

| Step | Action | UI Impact |
|------|--------|-----------|
| Register | `POST /api/auth/register` | Registration form with email, password, name |
| Verify email | `POST /api/auth/email/verify` | Verification status page, resend button |
| Login | `POST /api/auth/login` | Login form with email + password |
| Forgot password | `POST /api/auth/forgot-password` | Forgot password form, email sent |
| Reset password | `POST /api/auth/reset-password` | Reset password form with token |

**UI/UX Notes**:
- Registration requires email verification before account is usable
- Password reset uses a time-limited token sent via email
- Rate limiting applies to login and registration endpoints
- CAPTCHA may be required after repeated failed login attempts

### 2. Authentication (JWT + Refresh Tokens)

| Token Type | Algorithm | Lifetime | Storage |
|-----------|-----------|----------|---------|
| Access Token | JWT (HS256) | Short (minutes) | Memory (Zustand) |
| Refresh Token | JWT (HS256) | Longer (days) | Redis + localStorage |

**Flow**:
1. User logs in → receives access_token + refresh_token
2. Access token used for API requests
3. On 401, frontend calls `/api/auth/refresh` with refresh_token
4. New access_token issued; original request retried
5. On refresh failure → redirect to login

**UI/UX Notes**:
- Token refresh is transparent to the user
- Session management page shows active sessions with device info
- "Logout all devices" invalidates all refresh tokens

### 3. Two-Factor Authentication (2FA)

| Action | Endpoint | UI Component |
|--------|----------|-------------|
| Setup TOTP | `POST /api/auth/2fa/totp/setup` | QR code display |
| Enable 2FA | `POST /api/auth/2fa/totp/enable` | TOTP input form |
| Disable 2FA | `POST /api/auth/2fa/totp/disable` | Confirmation dialog |
| View backup codes | `GET /api/auth/2fa/backup-codes` | Backup codes list |
| Regenerate codes | `POST /api/auth/2fa/backup-codes/regenerate` | Confirmation dialog |

**UI/UX Notes**:
- TOTP setup shows a QR code for authenticator apps (Google Authenticator, Authy)
- Backup codes should be displayed once and downloadable
- 2FA is required on login if enabled (TOTP input field appears after password)
- Account recovery uses backup codes

### 4. Role-Based Access Control (RBAC)

**Roles**:
| Role | Description | Typical User |
|------|-------------|-------------|
| `admin` | Full system access | School administrators |
| `principal` | School management | School principals |
| `teacher` | Teaching operations | Teachers |
| `student` | Learning operations | Students |
| `parent` | Child management | Parents/guardians |
| `accountant` | Financial operations | School accountants |

**Permissions**: Fine-grained permissions like `record discipline`, `manage finances` are assigned to roles.

**UI/UX Notes**:
- UI must conditionally render actions based on user role
- Admin-only features should be hidden (not just disabled) for non-admin users
- Permission-based features use `role_or_permission` middleware

### 5. Session Management

| Action | Endpoint | Description |
|--------|----------|-------------|
| List sessions | `GET /api/auth/sessions` | Show all active sessions with device info |
| Destroy session | `DELETE /api/auth/sessions/{id}` | Revoke a specific session |
| Logout | `POST /api/auth/logout` | Destroy current session |
| Logout all | `POST /api/auth/logout/all` | Destroy all sessions |

**UI/UX Notes**:
- Session list shows device name, IP address, user agent, login time
- Each session has a revoke button
- "Logout all" requires confirmation

### 6. Password Management

| Action | Endpoint | Description |
|--------|----------|-------------|
| Change password | `PUT /api/auth/password/change` | Requires current password |
| Forgot password | `POST /api/auth/forgot-password` | Sends reset email |
| Reset password | `POST /api/auth/reset-password` | Sets new password with token |

**UI/UX Notes**:
- Change password requires current password confirmation
- Password strength validation on reset
- Success/failure feedback via toast notifications

### 7. Audit Logging

All sensitive actions are logged in the `audit_logs` table:
- User login/logout
- Password changes
- Role/permission assignments
- Disciplinary actions
- Financial operations

**UI/UX Notes**:
- Audit logs are for admin review only
- No direct user-facing UI for audit logs
- Used for compliance and security monitoring

## Models Summary

| Model | Key Fields |
|-------|-----------|
| User | id, name, email, password_hash, phone, totp_secret, totp_enabled, status |
| Profile | user_id, full_name, phone_number, birthday, gender, address |
| Role | id, name, description, is_active |
| Permission | id, name, description, guard_name |
| RefreshToken | user_id, token_hash, device_info, expires_at, revoked_at |
| UserSession | user_id, refresh_token_id, device_name, ip_address, user_agent |
| EmailVerification | user_id, token_hash, expires_at |
| PasswordResetToken | user_id, token_hash, expires_at |
| BackupCode | user_id, code_hash, is_used |
| AuditLog | user_id, action, ip_address, user_agent, metadata |

## Related Microservices

- **notify**: Sends verification emails, password reset emails, 2FA codes
- **realtime**: WebSocket connections for real-time session events