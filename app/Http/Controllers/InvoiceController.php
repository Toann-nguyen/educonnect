<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreInvoiceRequest;
use App\Http\Requests\UpdateInvoiceRequest;
use App\Http\Resources\InvoiceResouce;
use App\Http\Resources\InvoiceResource;
use App\Models\Invoice;
use App\Services\Interface\InvoiceServiceInterface;
use Illuminate\Http\Request;

class InvoiceController extends Controller
{
    protected $invoiceService;

    public function __construct(InvoiceServiceInterface $invoiceService)
    {
        $this->invoiceService = $invoiceService;
    }

    /**
     * Get my invoices (Student/Parent/Teacher)
     * Endpoint: GET /api/my-invoices
     */
    public function myInvoices(Request $request): JsonResponse
    {
        $invoices = $this->invoiceService->getMyInvoices($request->user());
        return response()->json([
            'data' => InvoiceResource::collection($invoices)
        ]);
    }

    /**
     * Display a listing of all invoices (Admin/Principal/Accountant)
     * Endpoint: GET /api/invoices
     */
    public function index(Request $request): JsonResponse
    {
        dd($request->user());
        $invoices = $this->invoiceService->getAllInvoices(
            $request->all(),
            $request->user()
        );
        return response()->json(InvoiceResource::collection($invoices));
    }

    /**
     * Store a newly created invoice
     * Endpoint: POST /api/invoices
     */
    public function store(StoreInvoiceRequest $request): JsonResponse
    {
        $invoice = $this->invoiceService->createInvoice(
            $request->validated(),
            $request->user()
        );
        return response()->json(new InvoiceResource($invoice), 201);
    }

    /**
     * Display the specified invoice
     * Endpoint: GET /api/invoices/{id}
     */
    public function show(Request $request, int $id): JsonResponse
    {
        $invoice = $this->invoiceService->getInvoiceById($id, $request->user());
        return response()->json(new InvoiceResource($invoice));
    }

    /**
     * Update the specified invoice
     * Endpoint: PUT/PATCH /api/invoices/{id}
     */
    public function update(UpdateInvoiceRequest $request, int $id): JsonResponse
    {
        $invoice = $this->invoiceService->updateInvoice(
            $id,
            $request->validated(),
            $request->user()
        );
        return response()->json(new InvoiceResource($invoice));
    }

    /**
     * Remove the specified invoice
     * Endpoint: DELETE /api/invoices/{id}
     */
    public function destroy(Request $request, int $id): JsonResponse
    {
        $this->invoiceService->deleteInvoice($id, $request->user());
        return response()->json(null, 204);
    }

    /**
     * Get invoices by class (Admin/Principal/Accountant/Homeroom Teacher)
     * Endpoint: GET /api/classes/{classId}/invoices
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
     * Get overdue invoices (Admin/Principal/Accountant)
     * Endpoint: GET /api/invoices/overdue
     */
    public function overdue(Request $request): JsonResponse
    {
        $invoices = $this->invoiceService->getOverdueInvoices($request->user());
        return response()->json([
            'data' => InvoiceResource::collection($invoices)
        ]);
    }

    /**
     * Get invoice statistics (Admin/Principal/Accountant)
     * Endpoint: GET /api/invoices/statistics
     */
    public function statistics(Request $request): JsonResponse
    {
        $stats = $this->invoiceService->getStatistics(
            $request->all(),
            $request->user()
        );
        return response()->json($stats);
    }
}
