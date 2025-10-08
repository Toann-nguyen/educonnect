<?php

namespace App\Http\Controllers;

use App\Http\Resources\InvoiceResource;
use App\Models\Invoice;
use App\Services\Interface\InvoiceServiceInterface;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Log;

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
        try {
            $invoices = $this->invoiceService->getAllInvoices(
                $request->all(),
                $request->user()
            );

            return response()->json([
                'message' => 'Invoices retrieved successfully',
                'data' => InvoiceResource::collection($invoices),
                'pagination' => [
                    'total' => $invoices->total(),
                    'per_page' => $invoices->perPage(),
                    'current_page' => $invoices->currentPage(),
                    'last_page' => $invoices->lastPage(),
                ]
            ], 200);
        } catch (\Exception $e) {
            Log::error('Error fetching invoices', [
                'error' => $e->getMessage(),
                'user_id' => $request->user()->id
            ]);

            return response()->json([
                'message' => 'Error fetching invoices',
                'error' => $e->getMessage()
            ], 500);
        }
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

        try {
            $invoice = $this->invoiceService->createInvoice(
                $validated,
                $request->user()
            );

            return response()->json([
                'message' => 'Invoice created successfully',
                'data' => new InvoiceResource($invoice)
            ], 201);
        } catch (\Exception $e) {
            Log::error('Error creating invoice', [
                'error' => $e->getMessage(),
                'data' => $validated
            ]);

            return response()->json([
                'message' => 'Error creating invoice',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Display the specified invoice
     * GET /api/invoices/{invoice}
     */
    public function show(Request $request, Invoice $invoice): JsonResponse
    {
        try {
            // Kiểm tra quyền trực tiếp
            if (!$this->invoiceService->canView($invoice, $request->user())) {
                return response()->json([
                    'message' => 'Unauthorized to view this invoice'
                ], 403);
            }

            // Load relationships
            $invoice->load([
                'student.user.profile',
                'student.guardians.guardian.profile',
                'student.schoolClass',
                'feeTypes',
                'payments.payer.profile',
                'issuer.profile'
            ]);

            return response()->json([
                'message' => 'Invoice retrieved successfully',
                'data' => new InvoiceResource($invoice)
            ], 200);
        } catch (\Exception $e) {
            Log::error('Error fetching invoice', [
                'invoice_id' => $invoice->id,
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'message' => 'Error fetching invoice',
                'error' => $e->getMessage()
            ], 500);
        }
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

        try {
            $invoice = $this->invoiceService->updateInvoice(
                $id,
                $validated,
                $request->user()
            );

            return response()->json([
                'message' => 'Invoice updated successfully',
                'data' => new InvoiceResource($invoice)
            ], 200);
        } catch (\Exception $e) {
            Log::error('Error updating invoice', [
                'invoice_id' => $id,
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'message' => 'Error updating invoice',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Remove the specified invoice
     * DELETE /api/invoices/{id}
     * Permissions: admin, principal, accountant
     */
    public function destroy(Request $request, int $id): JsonResponse
    {
        try {
            $this->invoiceService->deleteInvoice($id, $request->user());

            return response()->json([
                'message' => 'Invoice deleted successfully'
            ], 200);
        } catch (\Exception $e) {
            Log::error('Error deleting invoice', [
                'invoice_id' => $id,
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'message' => 'Error deleting invoice',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get my invoices (Student/Parent)
     * GET /api/my-invoices
     */
    public function myInvoices(Request $request): JsonResponse
    {
        try {
            $invoices = $this->invoiceService->getMyInvoices($request->user());
            return response()->json([
                'message' => 'My invoices retrieved successfully',
                'data' => InvoiceResource::collection($invoices),
                'count' => $invoices->count()
            ], 200);
        } catch (\Exception $e) {
            Log::error('Error fetching my invoices', [
                'user_id' => $request->user()->id,
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'message' => 'Error fetching my invoices',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get invoices by class (Admin/Principal/Accountant/Homeroom Teacher)
     * GET /api/invoices/class/{classId}
     */
    public function getByClass(Request $request, int $classId): JsonResponse
    {
        try {
            $invoices = $this->invoiceService->getInvoicesByClass(
                $classId,
                $request->all(),
                $request->user()
            );

            return response()->json([
                'message' => 'Class invoices retrieved successfully',
                'data' => InvoiceResource::collection($invoices),
                'count' => $invoices->count()
            ], 200);
        } catch (\Exception $e) {
            Log::error('Error fetching class invoices', [
                'class_id' => $classId,
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'message' => 'Error fetching class invoices',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get overdue invoices
     * GET /api/invoices/overdue
     * Permissions: admin, principal, accountant
     */
    public function getOverdue(Request $request): JsonResponse
    {
        try {
            $invoices = $this->invoiceService->getOverdueInvoices($request->user());

            return response()->json([
                'message' => 'Overdue invoices retrieved successfully',
                'data' => InvoiceResource::collection($invoices),
                'count' => $invoices->count()
            ], 200);
        } catch (\Exception $e) {
            Log::error('Error fetching overdue invoices', [
                'user_id' => $request->user()->id,
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'message' => 'Error fetching overdue invoices',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get invoice statistics
     * GET /api/invoices/statistics
     * Permissions: admin, principal, accountant
     */
    public function statistics(Request $request): JsonResponse
    {
        try {
            $stats = $this->invoiceService->getStatistics(
                $request->all(),
                $request->user()
            );

            // Đảm bảo luôn trả về data, không bao giờ null
            if (!$stats || empty($stats)) {
                $stats = [
                    'total_invoices' => 0,
                    'total_amount' => 0,
                    'total_paid' => 0,
                    'total_remaining' => 0,
                    'unpaid_count' => 0,
                    'partially_paid_count' => 0,
                    'paid_count' => 0,
                    'overdue_count' => 0,
                ];
            }

            return response()->json([
                'message' => 'Invoice statistics retrieved successfully',
                'data' => $stats
            ], 200);
        } catch (\Exception $e) {
            Log::error('Error fetching invoice statistics', [
                'user_id' => $request->user()->id,
                'filters' => $request->all(),
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'message' => 'Error fetching statistics',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Bulk create invoices for a class
     * POST /api/invoices/bulk-create
     * Permissions: admin, principal, accountant
     */
    public function bulkCreate(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'class_id' => 'required|exists:classes,id',
            'title' => 'nullable|string|max:255',
            'notes' => 'nullable|string',
            'due_date' => 'required|date',
            'fee_types' => 'required|array|min:1',
            'fee_types.*.fee_type_id' => 'required|exists:fee_types,id',
            'fee_types.*.amount' => 'required|numeric|min:0',
            'fee_types.*.note' => 'nullable|string'
        ]);

        try {
            // Authorization check
            if (!$request->user()->hasRole(['admin', 'principal', 'accountant'])) {
                return response()->json([
                    'message' => 'Unauthorized'
                ], 403);
            }

            // Get all students in the class
            $students = \App\Models\Student::where('class_id', $validated['class_id'])->get();

            if ($students->isEmpty()) {
                return response()->json([
                    'message' => 'No students found in this class',
                    'count' => 0
                ], 404);
            }

            $createdInvoices = [];
            $errors = [];

            foreach ($students as $student) {
                try {
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
                } catch (\Exception $e) {
                    $errors[] = [
                        'student_id' => $student->id,
                        'student_code' => $student->student_code,
                        'error' => $e->getMessage()
                    ];
                }
            }

            $response = [
                'message' => 'Bulk invoice creation completed',
                'success_count' => count($createdInvoices),
                'error_count' => count($errors),
                'data' => InvoiceResource::collection(collect($createdInvoices))
            ];

            if (!empty($errors)) {
                $response['errors'] = $errors;
            }

            return response()->json($response, 201);
        } catch (\Exception $e) {
            Log::error('Error in bulk invoice creation', [
                'class_id' => $validated['class_id'] ?? null,
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'message' => 'Error creating bulk invoices',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Update overdue invoice statuses
     * POST /api/invoices/update-overdue
     * Permissions: admin, principal, accountant
     */
    public function updateOverdueStatuses(Request $request): JsonResponse
    {
        try {
            // Authorization check
            if (!$request->user()->hasRole(['admin', 'principal', 'accountant'])) {
                return response()->json([
                    'message' => 'Unauthorized'
                ], 403);
            }

            $overdueInvoices = Invoice::overdue()->get();
            $count = $overdueInvoices->count();

            // Invoices are already filtered by overdue scope
            // Status is managed by the model's updatePaymentStatus method

            return response()->json([
                'message' => "Found {$count} overdue invoices",
                'count' => $count,
                'data' => InvoiceResource::collection($overdueInvoices)
            ], 200);
        } catch (\Exception $e) {
            Log::error('Error updating overdue statuses', [
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'message' => 'Error updating overdue statuses',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}
