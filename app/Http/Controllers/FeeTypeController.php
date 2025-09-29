<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreFeeTypeRequest;
use App\Http\Requests\UpdateFeeTypeRequest;
use App\Http\Resources\FeeTypeResource;
use App\Models\FeeType;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Auth\Access\AuthorizationException;

class FeeTypeController extends Controller
{
    /**
     * Display a listing of fee types
     * Endpoint: GET /api/fee-types
     * All authenticated users can view
     */
    public function index(Request $request): JsonResponse
    {
        $query = FeeType::query();

        // Filter by active status
        if ($request->has('is_active')) {
            $query->where('is_active', $request->boolean('is_active'));
        }

        // Search by name or code
        if ($request->has('search')) {
            $search = $request->input('search');
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('code', 'like', "%{$search}%");
            });
        }

        $feeTypes = $query->orderBy('name')->get();

        return response()->json([
            'data' => FeeTypeResource::collection($feeTypes)
        ]);
    }

    /**
     * Store a newly created fee type
     * Endpoint: POST /api/fee-types
     * Only Admin/Principal/Accountant
     */
    public function store(StoreFeeTypeRequest $request): JsonResponse
    {
        if (!$request->user()->hasRole(['admin', 'principal', 'accountant'])) {
            throw new AuthorizationException('You do not have permission to create fee types.');
        }

        $feeType = FeeType::create($request->validated());

        return response()->json(new FeeTypeResource($feeType), 201);
    }

    /**
     * Display the specified fee type
     * Endpoint: GET /api/fee-types/{id}
     */
    public function show(FeeType $feeType): JsonResponse
    {
        return response()->json(new FeeTypeResource($feeType));
    }

    /**
     * Update the specified fee type
     * Endpoint: PUT/PATCH /api/fee-types/{id}
     * Only Admin/Principal/Accountant
     */
    public function update(UpdateFeeTypeRequest $request, FeeType $feeType): JsonResponse
    {
        if (!$request->user()->hasRole(['admin', 'principal', 'accountant'])) {
            throw new AuthorizationException('You do not have permission to update fee types.');
        }

        $feeType->update($request->validated());

        return response()->json(new FeeTypeResource($feeType));
    }

    /**
     * Remove the specified fee type
     * Endpoint: DELETE /api/fee-types/{id}
     * Only Admin/Principal
     */
    public function destroy(Request $request, FeeType $feeType): JsonResponse
    {
        if (!$request->user()->hasRole(['admin', 'principal'])) {
            throw new AuthorizationException('You do not have permission to delete fee types.');
        }

        // Check if fee type is used in any invoices
        if ($feeType->invoices()->exists()) {
            return response()->json([
                'message' => 'Cannot delete fee type that is used in invoices. Consider deactivating it instead.'
            ], 422);
        }

        $feeType->delete();

        return response()->json(null, 204);
    }

    /**
     * Toggle active status
     * Endpoint: PATCH /api/fee-types/{id}/toggle-active
     * Only Admin/Principal/Accountant
     */
    public function toggleActive(Request $request, FeeType $feeType): JsonResponse
    {
        if (!$request->user()->hasRole(['admin', 'principal', 'accountant'])) {
            throw new AuthorizationException('You do not have permission to modify fee types.');
        }

        $feeType->is_active = !$feeType->is_active;
        $feeType->save();

        return response()->json(new FeeTypeResource($feeType));
    }
}
