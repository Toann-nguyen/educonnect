# Route Testing Guide

## 🧪 Quick Verification

### 1. Check Routes are Registered

```bash
# Invoice routes
php artisan route:list --path=invoices

# Expected output:
# GET    /api/my-invoices
# GET    /api/invoices/overdue
# GET    /api/invoices/statistics
# GET    /api/classes/{classId}/invoices
# GET    /api/invoices
# GET    /api/invoices/{id}
# POST   /api/invoices
# PUT    /api/invoices/{id}
# DELETE /api/invoices/{id}

# Payment routes
php artisan route:list --path=payments

# Expected output:
# GET    /api/payments/statistics
# GET    /api/invoices/{invoiceId}/payments
# GET    /api/payments
# GET    /api/payments/{payment}
# POST   /api/payments
# DELETE /api/payments/{id}

# Fee type routes
php artisan route:list --path=fee-types

# Expected output:
# GET    /api/fee-types
# GET    /api/fee-types/{feeType}
# POST   /api/fee-types
# PUT    /api/fee-types/{feeType}
# DELETE /api/fee-types/{feeType}
# PATCH  /api/fee-types/{feeType}/toggle-active
```

### 2. Test with Postman Collection

### Environment Variables

```json
{
    "base_url": "http://localhost:8000/api",
    "admin_token": "",
    "accountant_token": "",
    "teacher_token": "",
    "parent_token": "",
    "student_token": ""
}
```

### Collection Structure

```
📁 EduConnect API
  📁 1. Authentication
    ✉️ Login as Admin
    ✉️ Login as Accountant
    ✉️ Login as Teacher
    ✉️ Login as Parent
    ✉️ Login as Student

  📁 2. Fee Types
    ✉️ GET All Fee Types
    ✉️ GET Fee Type by ID
    ✉️ POST Create Fee Type (Admin)
    ✉️ PUT Update Fee Type (Admin)
    ✉️ DELETE Fee Type (Admin)
    ✉️ PATCH Toggle Active (Admin)

  📁 3. Invoices
    📁 3.1 View
      ✉️ GET All Invoices (Accountant)
      ✉️ GET Invoice by ID
      ✉️ GET My Invoices (Student)
      ✉️ GET My Invoices (Parent)
      ✉️ GET Invoices by Class (Teacher)

    📁 3.2 Special
      ✉️ GET Overdue Invoices (Accountant)
      ✉️ GET Statistics (Accountant)

    📁 3.3 Manage
      ✉️ POST Create Invoice (Accountant)
      ✉️ PUT Update Invoice (Accountant)
      ✉️ DELETE Invoice (Accountant)

  📁 4. Payments
    ✉️ GET All Payments (Accountant)
    ✉️ GET Payment by ID
    ✉️ GET Payments by Invoice
    ✉️ GET Payment Statistics (Accountant)
    ✉️ POST Create Payment (Parent)
    ✉️ POST Create Payment (Accountant)
    ✉️ DELETE Payment (Admin)
```

## ✅ Expected Behaviors

### Role: Admin/Principal/Accountant

-   ✅ Can view all invoices
-   ✅ Can create/update/delete invoices
-   ✅ Can view statistics
-   ✅ Can view overdue invoices
-   ✅ Can create/delete payments
-   ✅ Can manage fee types

### Role: Teacher (Homeroom)

-   ✅ Can view invoices of their class
-   ✅ Can view payments of their class invoices
-   ❌ Cannot create/update/delete invoices
-   ❌ Cannot view all invoices

### Role: Parent

-   ✅ Can view invoices of their children
-   ✅ Can create payments for their children
-   ✅ Can view payment history
-   ❌ Cannot view other students' invoices
-   ❌ Cannot delete payments

### Role: Student

-   ✅ Can view their own invoices
-   ✅ Can view payment history
-   ❌ Cannot create payments
-   ❌ Cannot view other students' invoices
-   ❌ Cannot access statistics

## 📊 Success Metrics

After merging routes, verify:

-   [ ] All old API endpoints still work
-   [ ] New invoice/payment endpoints work
-   [ ] Role-based authorization works correctly
-   [ ] No route conflicts (check with `route:list`)
-   [ ] Service layer authorization is triggered
-   [ ] All tests pass
-   [ ] Postman collection updated
-   [ ] Documentation updated
