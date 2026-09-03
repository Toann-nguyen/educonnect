# Microservices — Business Logic

## Overview

EduConnect uses three Go/Node.js microservices alongside the main Laravel application. These services handle cross-cutting concerns that benefit from independent scaling and deployment.

## Service Architecture

```
┌─────────────┐     ┌─────────────┐     ┌─────────────┐
│  Laravel    │────▶│  Finance    │     │  Notify     │
│  (Main App) │     │  (Go)       │     │  (Go)       │
│             │     │             │     │             │
│  Identity   │     │ Invoices    │     │ Emails      │
│  School     │     │ Payments    │     │ SMS (Twilio)│
│  Finance    │     │ Fee Types   │     │ Notifications│
└─────────────┘     └─────────────┘     └─────────────┘
                           │                    │
                           ▼                    ▼
                    ┌─────────────┐     ┌─────────────┐
                    │  RabbitMQ   │     │  RabbitMQ   │
                    │  (Queue)    │     │  (Queue)    │
                    └─────────────┘     └─────────────┘

┌─────────────┐
│  Realtime   │  (Node.js + Socket.IO)
│  (WebSocket)│
└─────────────┘
```

## Finance Service (`services/finance/`)

### Purpose
Handles financial operations independently from the main Laravel app.

### Tech Stack
- **Language**: Go
- **Framework**: Gin
- **Database**: GORM (same finance DB as Laravel)
- **Queue**: RabbitMQ (consumes user sync events)

### Key Components

| Component | Purpose |
|-----------|---------|
| `FeeType` struct | Fee type definition (code, name, default_amount, description, is_active) |
| `Invoice` struct | Invoice with student_id, issued_by, total_amount, paid_amount, due_date, status |
| `Payment` struct | Payment record with invoice_id, payer_user_id, amount_paid, payment_method |
| `UserReadModel` | Denormalized user data synced from Laravel via RabbitMQ |

### User Sync Consumer

The finance service consumes user events from RabbitMQ to maintain a read model of users:

```
Laravel (User created/updated) → RabbitMQ → Finance Service (update read model)
```

This allows the finance service to look up user information without joining across databases.

### API Routes (Go)

| Method | Endpoint | Description | Auth |
|--------|----------|-------------|------|
| GET | `/fee-types` | List fee types | JWT |
| GET | `/fee-types/:id` | Get fee type by ID | JWT |
| POST | `/fee-types` | Create fee type | JWT + admin|principal|accountant |
| PUT/PATCH | `/fee-types/:id` | Update fee type | JWT + admin|principal|accountant |
| DELETE | `/fee-types/:id` | Delete fee type | JWT + admin|principal|accountant |
| PATCH | `/fee-types/:id/toggle-active` | Toggle active status | JWT + admin|principal|accountant |
| POST | `/fee-types/:id/restore` | Restore soft-deleted fee type | JWT + admin|principal|accountant |
| GET | `/my-invoices` | List invoices for current user | JWT |
| GET | `/invoices/overdue` | List overdue invoices | JWT + admin|principal|accountant |
| GET | `/invoices/statistics` | Invoice statistics | JWT + admin|principal|accountant |
| POST | `/invoices/bulk-create` | Bulk create invoices | JWT + admin|principal|accountant |
| POST | `/invoices/update-overdue` | Update overdue statuses | JWT + admin|principal|accountant |
| GET | `/invoices/class/:classId` | Get invoices by class | JWT + admin|principal|accountant |
| GET | `/invoices` | List all invoices | JWT + admin|principal|accountant |
| GET | `/invoices/:id` | Get invoice detail | JWT + admin|principal|accountant |
| GET | `/invoices/:id/payments` | Get payments for invoice | JWT + admin|principal|accountant |
| POST | `/invoices` | Create invoice | JWT + admin|principal|accountant |
| PUT/PATCH | `/invoices/:id` | Update invoice | JWT + admin|principal|accountant |
| DELETE | `/invoices/:id` | Delete invoice | JWT + admin|principal|accountant |
| GET | `/payments/statistics` | Payment statistics | JWT + admin|principal|accountant |
| GET | `/payments` | List payments | JWT + admin|principal|accountant |
| GET | `/payments/:id` | Get payment detail | JWT + admin|principal|accountant |
| POST | `/payments` | Create payment | JWT + admin|principal|accountant |
| DELETE | `/payments/:id` | Delete payment | JWT + admin|principal|accountant |
| GET | `/financial-reports` | Financial reports | JWT + admin|principal|accountant |

### Business Logic

1. **Invoice Creation**: Validates student exists, calculates total from line items, sets status to `pending`
2. **Payment Processing**: Validates invoice exists and is not fully paid, updates `paid_amount` and `status`
3. **Overdue Detection**: Compares `due_date` with current date, auto-updates status to `overdue`
4. **Bulk Operations**: Batch invoice creation for multiple students
5. **Statistics**: Aggregates invoice/payment data for dashboard reporting

## Notify Service (`services/notify/`)

### Purpose
Handles all notification delivery (email, SMS) asynchronously via RabbitMQ.

### Tech Stack
- **Language**: Go
- **Queue**: RabbitMQ (producer-consumer pattern)
- **Email**: AWS SES (via SMTP or SDK)
- **SMS**: Twilio SDK

### Key Components

| Component | Purpose |
|-----------|---------|
| `NotifyPayload` | Notification message with type, recipient, template, data |
| `consume()` | RabbitMQ consumer that processes notification queue |
| `sendEmail()` | Sends email via AWS SES |
| `setupHTTP()` | HTTP endpoint for direct notification requests |

### Notification Flow

```
Laravel App → RabbitMQ (notification_queue) → Notify Service → Email/SMS
```

### Notification Types

| Type | Trigger | Channel |
|------|---------|---------|
| Email Verification | User registers | Email |
| Password Reset | User requests reset | Email |
| 2FA Code | User logs in with 2FA | Email/SMS |
| Invoice Notification | Invoice created/updated | Email |
| Payment Confirmation | Payment received | Email |
| Event Reminder | Upcoming event | Email/SMS |
| Attendance Alert | Absent/student marked late | Email/SMS |
| Discipline Notification | Discipline recorded | Email |

### Business Logic

1. **Queue Consumer**: Listens on `notification_queue` for new notification messages
2. **Template Rendering**: Uses email templates with dynamic data (student name, invoice amount, etc.)
3. **Delivery**: Routes to email (SES) or SMS (Twilio) based on notification type and recipient preferences
4. **Retry**: Failed deliveries are retried with exponential backoff
5. **Rate Limiting**: SMS delivery is rate-limited to prevent abuse

## Realtime Service (`services/realtime/`)

### Purpose
Provides real-time communication via WebSocket (Socket.IO) for live updates.

### Tech Stack
- **Language**: Node.js
- **Framework**: Express + Socket.IO
- **Queue**: RabbitMQ (consumes notification events)
- **Auth**: JWT verification on socket connection

### Key Components

| Component | Purpose |
|-----------|---------|
| Socket.IO Server | WebSocket server at `/socket.io/` |
| JWT Middleware | Verifies socket connection tokens |
| Room System | Users join rooms by `user:${userId}` and `admins` |
| RabbitMQ Consumer | Consumes notification events and emits to sockets |

### Connection Flow

```
Frontend → Socket.IO connection with JWT → Server verifies token →
User joins room (user:${userId}) → Admin joins room (admins) →
RabbitMQ notification → Server emits to relevant room →
Frontend receives real-time update
```

### Business Logic

1. **Authentication**: Socket connections are authenticated via JWT token
2. **Room Management**: Users join their personal room and admin room
3. **Notification Delivery**: When a notification is queued in RabbitMQ, the service emits it to the relevant user's room
4. **Real-time Updates**: Schedule changes, attendance updates, and discipline events are pushed to connected clients

### Use Cases for Frontend

| Event | Emitted To | UI Impact |
|-------|-----------|-----------|
| New notification | `user:${userId}` | Show toast/notification badge |
| Schedule updated | `user:${userId}` | Refresh schedule view |
| Attendance marked | `user:${userId}` | Update attendance status |
| Discipline recorded | `user:${userId}` | Show discipline alert |
| Invoice created | `user:${userId}` | Show invoice notification |
| Payment received | `user:${userId}` | Update invoice status |

## Cross-Service Communication

```
Laravel App ──(RabbitMQ)──▶ Finance Service
Laravel App ──(RabbitMQ)──▶ Notify Service
Laravel App ──(RabbitMQ)──▶ Realtime Service

Finance Service ──(RabbitMQ)──▶ Laravel App (user sync events)
Notify Service ──(RabbitMQ)──▶ Laravel App (delivery status)
Realtime Service ──(RabbitMQ)──▶ Laravel App (connection events)
```

## UI/UX Implications

### Real-time Updates
- Frontend should connect to Socket.IO on app initialization
- Notifications should appear as toast messages or badge updates
- Schedule/attendance views should auto-refresh on real-time events
- Loading states should be shown during real-time data updates

### Notification Preferences
- Users should be able to configure notification channels (email, SMS)
- Notification settings should be per-user
- Critical notifications (discipline, payment) should be immediate
- Non-critical notifications (events, reminders) can be batched

### Offline Handling
- If WebSocket disconnects, frontend should show a disconnect indicator
- Missed notifications should be fetched on reconnect
- Queue-based notifications should be delivered when the user comes back online