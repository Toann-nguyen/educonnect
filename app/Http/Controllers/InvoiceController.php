<?php

namespace App\Http\Controllers;

use App\Http\Resources\InvoiceResource;
use App\Models\Invoice;
use App\Services\Interface\InvoiceServiceInterface;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class InvoiceController extends Controller
{
    protected $invoiceService;

    public function __construct(InvoiceServiceInterface $invoiceService)
    {
        $this->invoiceService = $invoiceService;
    }

    /**
     * Display a listing of invoices
     * GET /api/invoices
     * Permissions: admin, principal, accountant (full access), teacher (homeroom only)
     */
    public function index(Request $request): JsonResponse
    {
        $invoices = $this->invoiceService->getAllInvoices(
            $request->all(),
            $request->user()
        );

        return response()->json(InvoiceResource::collection($invoices));
    }

    /**
     * Store a newly created invoice
     * POST /api/invoices
     * Permissions: admin, principal, accountant
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'student_id' => 'required|exists:students,id',
            'title' => 'nullable|string|max:255',
            'notes' => 'nullable|string',
            'due_date' => 'required|date',
            'fee_types' => 'required|array|min:1',
            'fee_types.*.fee_type_id' => 'required|exists:fee_types,id',
            'fee_types.*.amount' => 'required|numeric|min:0',
            'fee_types.*.note' => 'nullable|string'
        ]);

        $invoice = $this->invoiceService->createInvoice(
            $validated,
            $request->user()
        );

        return response()->json(new InvoiceResource($invoice), 201);
    }

    /**
     * Display the specified invoice
     * GET /api/invoices/
     */
    public function show(Request $request, Invoice $invoice): JsonResponse
    {
        // Kiểm tra quyền trực tiếp
        if (!$this->invoiceService->canView($invoice, $request->user())) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        // Load relationships
        $invoice->load([
            'student.user.profile',
            'student.guardians.guardian.profile',
            'feeTypes',
            'payments.payer.profile',
            'issuer.profile'
        ]);

        return response()->json(new InvoiceResource($invoice));
    }

    /**
     * Update the specified invoice
     * PUT /api/invoices/{id}
     * Permissions: admin, principal, accountant
     */
    public function update(Request $request, int $id): JsonResponse
    {
        $validated = $request->validate([
            'title' => 'sometimes|string|max:255',
            'notes' => 'sometimes|string',
            'due_date' => 'sometimes|date',
            'status' => 'sometimes|in:unpaid,partially_paid,paid,cancelled',
            'fee_types' => 'sometimes|array|min:1',
            'fee_types.*.fee_type_id' => 'required_with:fee_types|exists:fee_types,id',
            'fee_types.*.amount' => 'required_with:fee_types|numeric|min:0',
            'fee_types.*.note' => 'nullable|string'
        ]);

        $invoice = $this->invoiceService->updateInvoice(
            $id,
            $validated,
            $request->user()
        );

        return response()->json(new InvoiceResource($invoice));
    }

    /**
     * Remove the specified invoice
     * DELETE /api/invoices/{id}
     * Permissions: admin, principal, accountant
     */
    public function destroy(Request $request, int $id): JsonResponse
    {
        $this->invoiceService->deleteInvoice($id, $request->user());
        return response()->json(['message' => 'Resource deleted successfully.'], 200);
    }

    /**
     * Get my invoices (Student/Parent)
     * GET /api/invoices/my
     */
    public function myInvoices(Request $request): JsonResponse
    {
        $invoices = $this->invoiceService->getMyInvoices($request->user());
        return response()->json([
            'data' => InvoiceResource::collection($invoices)
        ]);
    }

    /**
     * Get invoices by class (Admin/Principal/Accountant/Homeroom Teacher)
     * GET /api/invoices/class/{classId}
     */
    public function getByClass(Request $request, int $classId): JsonResponse
    {
        $invoices = $this->invoiceService->getInvoicesByClass(
            $classId,
            $request->all(),
            $request->user()
        );

        return response()->json([
            'data' => InvoiceResource::collection($invoices)
        ]);
    }

    /**
     * Get overdue invoices
     * GET /api/invoices/overdue
     * Permissions: admin, principal, accountant
     */
    public function getOverdue(Request $request): JsonResponse
    {
        $invoices = $this->invoiceService->getOverdueInvoices($request->user());
        return response()->json([
            'data' => InvoiceResource::collection($invoices)
        ]);
    }

    /**
     * Get invoice statistics
     * GET /api/invoices/statistics
     * Permissions: admin, principal, accountant
     */
    public function statistics(Request $request): JsonResponse
    {
        $stats = $this->invoiceService->getStatistics(
            $request->all(),
            $request->user()
        );

        return response()->json($stats);
    }

    /**
     * Bulk create invoices for a class
     * POST /api/invoices/bulk-create
     * Permissions: admin, principal, accountant
     */
    public function bulkCreate(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'class_id' => 'required|exists:school_classes,id',
            'title' => 'nullable|string|max:255',
            'notes' => 'nullable|string',
            'due_date' => 'required|date',
            'fee_types' => 'required|array|min:1',
            'fee_types.*.fee_type_id' => 'required|exists:fee_types,id',
            'fee_types.*.amount' => 'required|numeric|min:0',
            'fee_types.*.note' => 'nullable|string'
        ]);

        // Authorization check
        if (!$request->user()->hasRole(['admin', 'principal', 'accountant'])) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        // Get all students in the class
        $students = \App\Models\Student::where('class_id', $validated['class_id'])->get();

        $createdInvoices = [];
        foreach ($students as $student) {
            $invoiceData = [
                'student_id' => $student->id,
                'title' => $validated['title'] ?? null,
                'notes' => $validated['notes'] ?? null,
                'due_date' => $validated['due_date'],
                'fee_types' => $validated['fee_types']
            ];

            $invoice = $this->invoiceService->createInvoice(
                $invoiceData,
                $request->user()
            );

            $createdInvoices[] = $invoice;
        }

        return response()->json([
            'message' => 'Invoices created successfully',
            'count' => count($createdInvoices),
            'data' => InvoiceResource::collection(collect($createdInvoices))
        ], 201);
    }

    /**
     * Update overdue invoice statuses
     * POST /api/invoices/update-overdue
     * Permissions: admin, principal, accountant
     */
    public function updateOverdueStatuses(Request $request): JsonResponse
    {
        // Authorization check
        if (!$request->user()->hasRole(['admin', 'principal', 'accountant'])) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $overdueInvoices = Invoice::overdue()->get();
        $count = $overdueInvoices->count();

        // Invoices are already filtered by overdue scope
        // Status is managed by the model's updatePaymentStatus method

        return response()->json([
            'message' => "Found {$count} overdue invoices",
            'count' => $count
        ]);
    }
}
