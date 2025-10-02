<?php

namespace App\Http\Controllers;

use App\Models\FeeType;
use App\Services\Interface\FeeTypeServiceInterface;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use App\Http\Resources\FeeTypeResource;
use App\Http\Requests\StoreFeeTypeRequest;
use App\Http\Requests\UpdateFeeTypeRequest;

class FeeTypeController extends Controller
{
    protected $feeTypeService;

    public function __construct(FeeTypeServiceInterface $feeTypeService)
    {
        $this->feeTypeService = $feeTypeService;
    }
    /**
     * Display a listing of fee types
     * GET /api/fee-types
     */
    public function index(Request $request): JsonResponse
    {
        $feeTypes = $this->feeTypeService->getAllFeeTypes($request->all());
        return response()->json($feeTypes);
    }

    /**
     * Store a newly created fee type
     * POST /api/fee-types
     */
    public function store(StoreFeeTypeRequest $request): JsonResponse
    {
        $feeType = $this->feeTypeService->createFeeType($request->validated());
        return response()->json($feeType, 201);
    }

    /**
     * Display the specified fee type
     * GET /api/fee-types/{id}
     */
    public function show(FeeType $feeType): JsonResponse
    {

        return response()->json($feeType);
    }

    /**
     * Update the specified fee type
     * PUT /api/fee-types/{id}
     */
    public function update(UpdateFeeTypeRequest $request, FeeType $feeType): JsonResponse
    {
        $updatedFeeType = $this->feeTypeService->updateFeeType($feeType, $request->validated());
        return response()->json($updatedFeeType);
    }

    /**
     * Remove the specified fee type
     * DELETE /api/fee-types/{id}
     */
    public function destroy(FeeType $feeType): JsonResponse
    {
        try {
            $this->feeTypeService->deleteFeeType($feeType);
            return response()->json(null, 204);
        } catch (\Exception $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }
    }

    /**
     * Toggle active status of fee type
     * PATCH /api/fee-types/{id}/toggle-active
     */
    public function toggleActive(FeeType $feeType): JsonResponse
    {
        // Thêm Policy check cho action tùy chỉnh
        $this->authorize('update', $feeType);

        $updatedFeeType = $this->feeTypeService->toggleActiveStatus($feeType);
        return response()->json($updatedFeeType);
    }
    /**
     * Restore a soft-deleted fee type.
     *
     * @param int $id
     * @return \Illuminate\Http\JsonResponse
     */
    public function restore($id): JsonResponse
    {
        // Tìm bản ghi đã xóa để kiểm tra quyền
        $feeType = FeeType::withTrashed()->findOrFail($id);

        // Sử dụng Policy để kiểm tra quyền 'restore'
        $this->authorize('restore', $feeType);

        $restoredFeeType = $this->feeTypeService->restoreFeeType($id);

        if ($restoredFeeType) {
            return response()->json($restoredFeeType); // Hoặc dùng FeeTypeResource
        }

        return response()->json(['message' => 'Fee Type not found in trash or could not be restored.'], 404);
    }
}
