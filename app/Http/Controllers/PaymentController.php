<?php

namespace App\Http\Controllers;

use App\Http\Requests\StorePaymentRequest;
use App\Http\Resources\PaymentResource;
use App\Models\Payment;
use App\Services\Interface\PaymentServiceInterface;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class PaymentController extends Controller
{
    protected $paymentService;

    public function __construct(PaymentServiceInterface $paymentService)
    {
        $this->paymentService = $paymentService;
    }

    /**
     * Display a listing of all payments (Admin/Principal/Accountant)
     * Endpoint: GET /api/payments
     */
    public function index(Request $request): JsonResponse
    {
        $payments = $this->paymentService->getAllPayments(
            $request->all(),
            $request->user()
        );
        return response()->json(PaymentResource::collection($payments));
    }

    /**
     * Store a newly created payment
     * Endpoint: POST /api/payments
     */
    public function store(StorePaymentRequest $request): JsonResponse
    {
        $payment = $this->paymentService->createPayment(
            $request->validated(),
            $request->user()
        );
        return response()->json(new PaymentResource($payment), 201);
    }

    /**
     * Display the specified payment
     * Endpoint: GET /api/payments/{id}
     */
    public function show(Request $request, Payment $payment): JsonResponse
    {
        // Load relationships
        $payment->load(['invoice.student.user.profile', 'payer.profile', 'creator.profile']);
        return response()->json(new PaymentResource($payment));
    }

    /**
     * Remove the specified payment
     * Endpoint: DELETE /api/payments/{id}
     */
    public function destroy(Request $request, int $id): JsonResponse
    {
        $this->paymentService->deletePayment($id, $request->user());
        return response()->json(null, 204);
    }

    /**
     * Get payments for a specific invoice
     * Endpoint: GET /api/invoices/{invoiceId}/payments
     */
    public function getByInvoice(Request $request, int $invoiceId): JsonResponse
    {
        $payments = $this->paymentService->getPaymentsByInvoice(
            $invoiceId,
            $request->user()
        );
        return response()->json([
            'data' => PaymentResource::collection($payments)
        ]);
    }

    /**
     * Get payment statistics (Admin/Principal/Accountant)
     * Endpoint: GET /api/payments/statistics
     */
    public function statistics(Request $request): JsonResponse
    {
        $stats = $this->paymentService->getStatistics(
            $request->all(),
            $request->user()
        );
        return response()->json($stats);
    }
}
