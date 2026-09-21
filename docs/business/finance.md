# Finance — Business Domain

## Overview

The Finance domain manages school financial operations including fee types, invoice generation, and payment processing. It is currently the least developed domain in EduConnect — the HTTP layer (controllers, routes, request classes) is not yet implemented in the main Laravel application, but the service layer and models exist, and a separate Go-based microservice handles financial operations.

**Database connection**: `finance`

## Business Capabilities

### 1. Fee Types

**Purpose**: Define types of fees that can be charged to students (e.g., tuition, library fee, lab fee, activity fee).

| Action | Who | UI Notes |
|--------|-----|----------|
| Create fee type | Admin/Principal/Accountant | Form with code, name, default_amount, description |
| List fee types | Admin/Principal/Accountant | Table with fee types and active status |
| View fee type | Admin/Principal/Accountant | Detail view |
| Update fee type | Admin/Principal/Accountant | Edit form |
| Delete fee type | Admin/Principal/Accountant | Soft delete |
| Toggle active | Admin/Principal/Accountant | Enable/disable fee type |
| Restore fee type | Admin/Principal/Accountant | Restore from trash |

**UI/UX Notes**:
- Fee types are master data — rarely changed
- `is_active` flag controls whether the fee type can be used in invoices
- Default amount is a template value; actual invoice amounts may differ
- Fee type code should be unique (e.g., "TUITION", "LIBRARY", "LAB")
- Should have validation to prevent deletion if referenced by existing invoices

### 2. Invoices

**Purpose**: Generate invoices for students/families, tracking what they owe.

| Action | Who | UI Notes |
|--------|-----|----------|
| Create invoice | Admin/Principal/Accountant | Form with student, fee items, due date, notes |
| List invoices | Admin/Principal/Accountant | Table with filters (student, status, date range) |
| View invoice | Admin/Principal/Accountant | Invoice detail with line items |
| View my invoices | Student/Parent | Personal invoice list |
| Update invoice | Admin/Principal/Accountant | Edit form |
| Delete invoice | Admin/Principal/Accountant | Confirmation dialog |
| Bulk create invoices | Admin/Principal/Accountant | Bulk upload or batch generation |
| Update overdue status | Admin/Principal/Accountant | Auto-mark overdue invoices |
| Get overdue invoices | Admin/Principal/Accountant | List of overdue invoices |
| Get invoices by class | Admin/Principal/Accountant | Class-level invoice report |
| Statistics | Admin/Principal/Accountant | Invoice statistics dashboard |

**Invoice Fields**:
| Field | Description |
|-------|-------------|
| `invoice_number` | Unique invoice identifier (auto-generated) |
| `student_id` | Student this invoice is for |
| `issued_by` | User who created the invoice |
| `total_amount` | Total invoice amount |
| `paid_amount` | Amount already paid |
| `due_date` | Payment deadline |
| `status` | pending, paid, overdue, partially_paid |
| `notes` | Additional notes |

**UI/UX Notes**:
- Invoice status workflow: pending → partially_paid → paid (or overdue)
- Overdue invoices should be highlighted in red
- Invoice detail shows line items (fee_type, amount) and payment history
- Bulk create is useful for semester-start tuition billing
- "My invoices" view for students/parents shows only their invoices
- Statistics should show revenue, outstanding balance, overdue counts

### 3. Payments

**Purpose**: Record payments made by students/families against invoices.

| Action | Who | UI Notes |
|--------|-----|----------|
| Create payment | Admin/Principal/Accountant | Form with invoice, amount, payment method, date |
| List payments | Admin/Principal/Accountant | Table with filters |
| View payment | Admin/Principal/Accountant | Payment detail |
| Update payment | Admin/Principal/Accountant | Edit form |
| Delete payment | Admin/Principal/Accountant | Confirmation dialog |
| View payments by invoice | Admin/Principal/Accountant | Payment history for an invoice |
| Statistics | Admin/Principal/Accountant | Payment statistics |
| Financial reports | Admin/Principal/Accountant | Financial report with role permission |

**Payment Fields**:
| Field | Description |
|-------|-------------|
| `invoice_id` | Invoice being paid |
| `payer_user_id` | User making the payment |
| `created_by_user_id` | User who recorded the payment |
| `amount_paid` | Payment amount |
| `payment_date` | Date of payment |
| `payment_method` | cash, bank_transfer, check, online |
| `transaction_code` | Bank transaction reference |
| `note` | Additional notes |

**UI/UX Notes**:
- Payment should auto-update the invoice's `paid_amount` and `status`
- Payment methods: cash, bank transfer, check, online payment
- Transaction code should be unique for bank transfers
- Payment history should be visible on the invoice detail page
- Financial reports should show revenue trends, payment methods breakdown
- Role `accountant` has special access to financial operations

### 4. Invoice Items (via Fee Types)

**Purpose**: Each invoice contains line items linking fee types to amounts.

| Field | Description |
|-------|-------------|
| `invoice_id` | Parent invoice |
| `fee_type_id` | Type of fee |
| `amount` | Amount for this line item |

**UI/UX Notes**:
- When creating an invoice, the user selects fee types and amounts
- The total is auto-calculated from line items
- Line items can be added/removed before the invoice is finalized

## Models Summary

| Model | Key Fields |
|-------|-----------|
| FeeType | code, name, default_amount, description, is_active |
| Invoice | invoice_number, student_id, issued_by, total_amount, paid_amount, due_date, status |
| InvoiceItem | invoice_id, fee_type_id, amount |
| Payment | invoice_id, payer_user_id, created_by_user_id, amount_paid, payment_date, payment_method, transaction_code |

## Cross-Domain Relationships

```
Identity.User ──(issued_by)──▶ Finance.Invoice
Identity.User ──(payer_user_id)──▶ Finance.Payment
Identity.User ──(created_by_user_id)──▶ Finance.Payment
School.Student ──(student_id)──▶ Finance.Invoice
School.Student ──(student_id)──▶ Finance.Payment (via invoice)
```

**Key design decision**: Finance domain references students via `student_id` (not `user_id`), and users via `user_id` for issued_by/payer/created_by fields.

## Go Microservice

The finance microservice (`services/finance/`) is a Go-based service that:
- Connects to the finance database via GORM
- Consumes user sync events from RabbitMQ
- Provides a separate API for financial operations
- Uses JWT middleware for authentication

**Why a separate microservice?**
- Financial operations may have different scaling requirements
- Isolation of sensitive financial data
- Independent deployment and scaling

## UI/UX Considerations

### Role-Based Access

| Feature | Admin | Principal | Accountant | Teacher | Student | Parent |
|---------|-------|-----------|-----------|---------|---------|--------|
| Fee type CRUD | ✓ | ✓ | ✓ | ✗ | ✗ | ✗ |
| Invoice CRUD | ✓ | ✓ | ✓ | ✗ | ✗ | ✗ |
| View my invoices | ✓ | ✓ | ✓ | ✗ | ✓ | ✓ |
| Payment CRUD | ✓ | ✓ | ✓ | ✗ | ✗ | ✗ |
| Financial reports | ✓ | ✓ | ✓ | ✗ | ✗ | ✗ |
| Overdue management | ✓ | ✓ | ✓ | ✗ | ✗ | ✗ |

### Workflow

1. **Invoice Creation**: Admin/Accountant creates invoice → selects student → adds fee type line items → sets due date → saves
2. **Payment Recording**: Admin/Accountant records payment → selects invoice → enters amount and method → saves → invoice status auto-updates
3. **Overdue Detection**: System auto-detects overdue invoices (due_date passed, status still pending/partially_paid)
4. **Reporting**: Admin/Principal/Accountant views financial statistics and reports

### Pending HTTP Layer

The Finance domain currently has **no HTTP controllers, routes, or request classes** in the main Laravel application. The service layer (InvoiceService, PaymentService, FeeTypeService) and models exist but are not yet exposed via HTTP endpoints. The Go microservice provides an alternative API path.

**TODO**: Implement HTTP layer for Finance domain to match the pattern of Identity and School domains.