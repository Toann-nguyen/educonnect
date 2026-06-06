<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Http\Resources\DisciplineTypeResource;
use App\Models\DisciplineType;
use Illuminate\Http\JsonResponse;

class DisciplineTypeController extends Controller
{
    /**
     * GET /api/discipline-types - Danh sách loại vi phạm
     */
    public function index(Request $request): JsonResponse
    {
        $query = DisciplineType::query();

        // Filter by active status
        if ($request->has('is_active')) {
            $query->where('is_active', $request->boolean('is_active'));
        }

        // Filter by severity level
        if ($request->has('severity_level')) {
            $query->where('severity_level', $request->input('severity_level'));
        }

        // Search by name or code
        if ($request->has('search')) {
            $search = $request->input('search');
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('code', 'like', "%{$search}%");
            });
        }

        $disciplineTypes = $query->orderBy('severity_level')
            ->orderBy('name')
            ->paginate($request->input('per_page', 50));

        return response()->json(
            DisciplineTypeResource::collection($disciplineTypes)->response()->getData()
        );
    }

    /**
     * GET /api/discipline-types/{id} - Chi tiết loại vi phạm
     */
    public function show(DisciplineType $disciplineType): JsonResponse
    {
        return response()->json(new DisciplineTypeResource($disciplineType));
    }

    /**
     * POST /api/discipline-types - Tạo loại mới (Admin only)
     */
    public function store(Request $request): JsonResponse
    {
        // Check admin permission
        if (!$request->user()->hasAnyRole(['admin', 'principal'])) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $validated = $request->validate([
            'code' => 'required|string|max:50|unique:discipline_types,code',
            'name' => 'required|string|max:255',
            'severity_level' => 'required|in:light,medium,serious,very_serious',
            'default_penalty_points' => 'required|integer|min:0|max:50',
            'description' => 'nullable|string',
            'is_active' => 'boolean',
        ]);

        $disciplineType = DisciplineType::create($validated);

        return response()->json([
            'message' => 'Loại vi phạm đã được tạo thành công',
            'data' => new DisciplineTypeResource($disciplineType)
        ], 201);
    }

    /**
     * PUT /api/discipline-types/{id} - Cập nhật (Admin only)
     */
    public function update(Request $request, DisciplineType $disciplineType): JsonResponse
    {
        // Check admin permission
        if (!$request->user()->hasAnyRole(['admin', 'principal'])) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $validated = $request->validate([
            'code' => 'sometimes|string|max:50|unique:discipline_types,code,' . $disciplineType->id,
            'name' => 'sometimes|string|max:255',
            'severity_level' => 'sometimes|in:light,medium,serious,very_serious',
            'default_penalty_points' => 'sometimes|integer|min:0|max:50',
            'description' => 'nullable|string',
            'is_active' => 'boolean',
        ]);

        $disciplineType->update($validated);

        return response()->json([
            'message' => 'Loại vi phạm đã được cập nhật',
            'data' => new DisciplineTypeResource($disciplineType)
        ]);
    }

    /**
     * DELETE /api/discipline-types/{id} - Xóa (Admin only)
     */
    public function destroy(Request $request, DisciplineType $disciplineType): JsonResponse
    {
        // Check admin permission
        if (!$request->user()->hasAnyRole(['admin', 'principal'])) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        // Check if being used
        if ($disciplineType->disciplines()->exists()) {
            return response()->json([
                'message' => 'Không thể xóa loại vi phạm đang được sử dụng. Hãy vô hiệu hóa thay vì xóa.'
            ], 400);
        }

        $disciplineType->delete();

        return response()->json(['message' => 'Loại vi phạm đã được xóa'], 200);
    }
}
