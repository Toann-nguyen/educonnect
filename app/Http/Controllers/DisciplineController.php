<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreDisciplineRequest;
use App\Http\Requests\UpdateDisciplineRequest;
use App\Models\Discipline;
use App\Http\Requests\AppealDisciplineRequest;
use App\Http\Requests\ReviewDisciplineRequest;
use App\Http\Resources\DisciplineResource;
use App\Services\Interface\DisciplineServiceInterface;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class DisciplineController extends Controller
{
    protected $disciplineService;

    public function __construct(DisciplineServiceInterface $disciplineService)
    {
        $this->disciplineService = $disciplineService;
    }

    /**
     * GET /api/disciplines - Danh sách kỷ luật
     */
    public function index(Request $request): JsonResponse
    {
        $disciplines = $this->disciplineService->getAllDisciplines($request->all());
        return response()->json(DisciplineResource::collection($disciplines)->response()->getData());
    }

    /**
     * GET /api/disciplines/my - Kỷ luật của tôi/con tôi
     */
    public function my(Request $request): JsonResponse
    {
        $filters = [];
        if ($request->has('student_id')) {
            $filters['student_id'] = $request->input('student_id');
        }

        $disciplines = $this->disciplineService->getMyDisciplines($request->user(), $filters);
        return response()->json(DisciplineResource::collection($disciplines)->response()->getData());
    }

    /**
     * GET /api/disciplines/class/{classId} - Kỷ luật theo lớp
     */
    public function byClass(Request $request, int $classId): JsonResponse
    {
        $disciplines = $this->disciplineService->getDisciplinesByClass($classId, $request->all());
        return response()->json(DisciplineResource::collection($disciplines)->response()->getData());
    }

    /**
     * GET /api/disciplines/student/{studentId} - Kỷ luật của một học sinh
     */
    public function byStudent(Request $request, int $studentId): JsonResponse
    {
        $disciplines = $this->disciplineService->getDisciplinesByStudent($studentId, $request->all());
        return response()->json(DisciplineResource::collection($disciplines)->response()->getData());
    }

    /**
     * POST /api/disciplines - Tạo bản ghi mới
     */
    public function store(StoreDisciplineRequest $request): JsonResponse
    {
        $discipline = $this->disciplineService->createDiscipline(
            $request->validated(),
            $request->user()
        );

        return response()->json([
            'message' => 'Bản ghi kỷ luật đã được tạo thành công',
            'data' => new DisciplineResource($discipline)
        ], 201);
    }

    /**
     * GET /api/disciplines/{id} - Chi tiết bản ghi
     */
    public function show(Request $request, Discipline $discipline): JsonResponse
    {
        // Check permission
        if (!$this->disciplineService->canAccess($request->user(), $discipline)) {
            return response()->json(['message' => 'Bạn không có quyền xem bản ghi này'], 403);
        }

        $discipline->load(['actions', 'appeals']);
        return response()->json(new DisciplineResource($discipline));
    }

    /**
     * PUT /api/disciplines/{id} - Cập nhật
     */
    public function update(UpdateDisciplineRequest $request, Discipline $discipline): JsonResponse
    {
        $updated = $this->disciplineService->updateDiscipline($discipline, $request->validated());
        return response()->json([
            'message' => 'Bản ghi đã được cập nhật',
            'data' => new DisciplineResource($updated)
        ]);
    }

    /**
     * DELETE /api/disciplines/{id} - Xóa (soft)
     */
    public function destroy(Request $request, Discipline $discipline): JsonResponse
    {
        // Only admin/principal can delete, or teacher can delete their own pending records
        if (!$this->disciplineService->canModify($request->user(), $discipline)) {
            return response()->json(['message' => 'Bạn không có quyền xóa bản ghi này'], 403);
        }

        $this->disciplineService->deleteDiscipline($discipline);
        return response()->json(['message' => 'Bản ghi đã được xóa'], 200);
    }

    /**
     * POST /api/disciplines/{id}/approve - Duyệt
     */
    public function approve(ReviewDisciplineRequest $request, Discipline $discipline): JsonResponse
    {
        if ($discipline->status !== 'pending') {
            return response()->json(['message' => 'Chỉ có thể duyệt bản ghi đang chờ'], 400);
        }

        $approved = $this->disciplineService->approveDiscipline(
            $discipline,
            $request->user(),
            $request->input('review_note')
        );

        return response()->json([
            'message' => 'Bản ghi đã được duyệt',
            'data' => new DisciplineResource($approved)
        ]);
    }

    /**
     * POST /api/disciplines/{id}/reject - Từ chối
     */
    public function reject(ReviewDisciplineRequest $request, Discipline $discipline): JsonResponse
    {
        if ($discipline->status !== 'pending') {
            return response()->json(['message' => 'Chỉ có thể từ chối bản ghi đang chờ'], 400);
        }

        if (!$request->has('review_note')) {
            return response()->json(['message' => 'Vui lòng nhập lý do từ chối'], 400);
        }

        $rejected = $this->disciplineService->rejectDiscipline(
            $discipline,
            $request->user(),
            $request->input('review_note')
        );

        return response()->json([
            'message' => 'Bản ghi đã bị từ chối',
            'data' => new DisciplineResource($rejected)
        ]);
    }

    /**
     * POST /api/disciplines/{id}/appeal - Khiếu nại
     */
    public function appeal(AppealDisciplineRequest $request, Discipline $discipline): JsonResponse
    {
        $success = $this->disciplineService->createAppeal(
            $discipline,
            $request->user(),
            $request->input('appeal_reason'),
            $request->input('evidence')
        );

        if ($success) {
            return response()->json(['message' => 'Khiếu nại đã được gửi thành công'], 201);
        }

        return response()->json(['message' => 'Không thể tạo khiếu nại'], 500);
    }

    /**
     * GET /api/disciplines/statistics - Thống kê
     */
    public function statistics(Request $request): JsonResponse
    {
        $stats = $this->disciplineService->getStatistics($request->all());
        return response()->json($stats);
    }

    /**
     * GET /api/disciplines/export - Export Excel/PDF
     */
    public function export(Request $request): JsonResponse
    {
        // TODO: Implement export functionality
        return response()->json(['message' => 'Export feature coming soon'], 501);
    }
}
