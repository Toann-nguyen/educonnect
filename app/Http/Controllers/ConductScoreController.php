<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Http\Resources\ConductScoreResource;
use App\Models\StudentConductScore;
use App\Services\Interface\ConductScoreServiceInterface;
use Illuminate\Http\JsonResponse;


class ConductScoreController extends Controller
{
    protected $conductScoreService;

    public function __construct(ConductScoreServiceInterface $conductScoreService)
    {
        $this->conductScoreService = $conductScoreService;
    }

    /**
     * GET /api/conduct-scores/my - Điểm hạnh kiểm của tôi/con tôi
     */
    public function my(Request $request): JsonResponse
    {
        $conductScores = $this->conductScoreService->getMyConductScores($request->user(), $request->all());
        return response()->json(ConductScoreResource::collection($conductScores)->response()->getData());
    }

    /**
     * GET /api/conduct-scores/class/{classId} - Điểm hạnh kiểm theo lớp
     */
    public function byClass(Request $request, int $classId): JsonResponse
    {
        // Only homeroom teacher, admin, principal can view
        $user = $request->user();
        if (!$user->hasAnyRole(['admin', 'principal'])) {
            $isHomeroom = \App\Models\SchoolClass::where('id', $classId)
                ->where('homeroom_teacher_id', $user->id)
                ->exists();

            if (!$isHomeroom) {
                return response()->json(['message' => 'Bạn không có quyền xem điểm lớp này'], 403);
            }
        }

        $conductScores = $this->conductScoreService->getConductScoresByClass($classId, $request->all());
        return response()->json(ConductScoreResource::collection($conductScores)->response()->getData());
    }

    /**
     * GET /api/conduct-scores/student/{studentId} - Điểm của một học sinh
     */
    public function byStudent(Request $request, int $studentId): JsonResponse
    {
        $semester = $request->input('semester');
        $academicYearId = $request->input('academic_year_id');

        if (!$semester || !$academicYearId) {
            return response()->json([
                'message' => 'Vui lòng cung cấp semester và academic_year_id'
            ], 400);
        }

        $conductScore = $this->conductScoreService->getStudentConductScore(
            $studentId,
            $semester,
            $academicYearId
        );

        if (!$conductScore) {
            return response()->json(['message' => 'Không tìm thấy điểm hạnh kiểm'], 404);
        }

        return response()->json(new ConductScoreResource($conductScore));
    }

    /**
     * PUT /api/conduct-scores/{id} - Cập nhật điểm hạnh kiểm (GVCN, Admin)
     */
    public function update(Request $request, StudentConductScore $conductScore): JsonResponse
    {
        $user = $request->user();

        // Check permission: Admin, Principal, or Homeroom teacher of the class
        if (!$user->hasAnyRole(['admin', 'principal'])) {
            $isHomeroom = $conductScore->student->schoolClass
                && $conductScore->student->schoolClass->homeroom_teacher_id === $user->id;

            if (!$isHomeroom) {
                return response()->json(['message' => 'Bạn không có quyền cập nhật'], 403);
            }
        }

        $validated = $request->validate([
            'teacher_comment' => 'nullable|string|max:2000',
            'total_penalty_points' => 'sometimes|integer|min:0',
        ]);

        $updated = $this->conductScoreService->updateConductScore(
            $conductScore->student_id,
            $conductScore->semester,
            $conductScore->academic_year_id,
            $validated
        );

        return response()->json([
            'message' => 'Điểm hạnh kiểm đã được cập nhật',
            'data' => new ConductScoreResource($updated)
        ]);
    }

    /**
     * POST /api/conduct-scores/{id}/approve - Phê duyệt điểm hạnh kiểm
     */
    public function approve(Request $request, StudentConductScore $conductScore): JsonResponse
    {
        // Only Admin/Principal can approve
        if (!$request->user()->hasAnyRole(['admin', 'principal'])) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        if ($conductScore->approved_at) {
            return response()->json(['message' => 'Điểm hạnh kiểm đã được phê duyệt'], 400);
        }

        $approved = $this->conductScoreService->approveConductScore($conductScore, $request->user());

        return response()->json([
            'message' => 'Điểm hạnh kiểm đã được phê duyệt',
            'data' => new ConductScoreResource($approved)
        ]);
    }

    /**
     * POST /api/conduct-scores/recalculate - Tính lại điểm hạnh kiểm
     */
    public function recalculate(Request $request): JsonResponse
    {
        // Only Admin/Principal/Homeroom can recalculate
        if (!$request->user()->hasAnyRole(['admin', 'principal', 'teacher'])) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $validated = $request->validate([
            'student_id' => 'required|integer|exists:students,id',
            'semester' => 'required|integer|in:1,2',
            'academic_year_id' => 'required|integer|exists:academic_years,id',
        ]);

        $conductScore = $this->conductScoreService->recalculateConductScore(
            $validated['student_id'],
            $validated['semester'],
            $validated['academic_year_id']
        );

        return response()->json([
            'message' => 'Điểm hạnh kiểm đã được tính lại',
            'data' => new ConductScoreResource($conductScore)
        ]);
    }
}
