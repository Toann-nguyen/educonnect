<?php

namespace App\Http\Controllers;

use App\Http\Resources\ConductScoreResource;
use App\Services\Interface\ConductScoreServiceInterface;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Log;

class ConductScoreController extends Controller
{
    protected $conductScoreService;

    public function __construct(ConductScoreServiceInterface $conductScoreService)
    {
        $this->conductScoreService = $conductScoreService;
    }

    /**
     * GET /api/conduct-scores/my - Điểm hạnh kiểm của tôi (Student/Parent)
     */
    public function my(Request $request): JsonResponse
    {
        try {
            $user = $request->user();
            $semester = $request->input('semester') ? (int) $request->input('semester') : null;
            $academicYearId = $request->input('academic_year_id') ? (int) $request->input('academic_year_id') : null;

            $conductScores = $this->conductScoreService->getMyConductScores(
                $user,
                $semester,
                $academicYearId
            );

            return response()->json([
                'message' => 'My conduct scores retrieved successfully',
                'data' => ConductScoreResource::collection($conductScores),
                'count' => $conductScores->count()
            ], 200);
        } catch (\Exception $e) {
            Log::error('Error fetching my conduct scores', [
                'user_id' => $request->user()->id,
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'message' => 'Error fetching conduct scores',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * GET /api/conduct-scores/class/{classId} - Điểm của cả lớp
     */
    public function byClass(Request $request, int $classId): JsonResponse
    {
        try {
            $semester = $request->input('semester');
            $academicYearId = $request->input('academic_year_id');

            $conductScores = $this->conductScoreService->getClassConductScores(
                $classId,
                $request->user(),
                $semester,
                $academicYearId
            );

            return response()->json([
                'message' => 'Class conduct scores retrieved successfully',
                'data' => ConductScoreResource::collection($conductScores),
                'count' => $conductScores->count(),
                'filters' => [
                    'class_id' => $classId,
                    'semester' => $semester,
                    'academic_year_id' => $academicYearId
                ]
            ], 200);
        } catch (\Exception $e) {
            Log::error('Error fetching class conduct scores', [
                'class_id' => $classId,
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'message' => 'Error fetching class conduct scores',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * GET /api/conduct-scores/student/{studentId} - Điểm của một học sinh
     *
     * Query Parameters (Optional):
     * - semester: int (1 hoặc 2) - Nếu không có, trả về tất cả
     * - academic_year_id: int - Nếu không có, trả về tất cả
     */
    public function byStudent(Request $request, int $studentId): JsonResponse
    {
        try {
            $semester = $request->input('semester');
            $academicYearId = $request->input('academic_year_id');

            // Nếu có đầy đủ semester và academic_year_id, trả về 1 record
            if ($semester && $academicYearId) {
                $conductScore = $this->conductScoreService->getStudentConductScore(
                    $studentId,
                    $semester,
                    $academicYearId
                );

                if (!$conductScore) {
                    return response()->json([
                        'message' => 'Không tìm thấy điểm hạnh kiểm',
                        'filters' => [
                            'student_id' => $studentId,
                            'semester' => $semester,
                            'academic_year_id' => $academicYearId
                        ]
                    ], 404);
                }

                return response()->json([
                    'message' => 'Student conduct score retrieved successfully',
                    'data' => new ConductScoreResource($conductScore)
                ], 200);
            }

            // Nếu không có đầy đủ parameters, trả về tất cả conduct scores của học sinh
            $conductScores = $this->conductScoreService->getAllStudentConductScores(
                $studentId,
                $semester,
                $academicYearId
            );

            return response()->json([
                'message' => 'Student conduct scores retrieved successfully',
                'data' => ConductScoreResource::collection($conductScores),
                'count' => $conductScores->count(),
                'filters' => [
                    'student_id' => $studentId,
                    'semester' => $semester,
                    'academic_year_id' => $academicYearId
                ]
            ], 200);
        } catch (\Exception $e) {
            Log::error('Error fetching student conduct scores', [
                'student_id' => $studentId,
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'message' => 'Error fetching student conduct scores',
                'error' => $e->getMessage()
            ], 500);
        }
    }
    /**
     * POST /api/conduct-scores - Tạo conduct score mới
     * Permissions: admin, principal, teacher (homeroom)
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'student_id' => 'required|integer|exists:students,id',
            'semester' => 'required|integer',
            'academic_year_id' => 'required|integer|exists:academic_years,id',
            'total_penalty_points' => 'sometimes|integer',
            'teacher_comment' => 'sometimes|string|max:1000',
        ]);

        if (isset($validated['total_penalty_points']) && $validated['total_penalty_points'] < 0) {
            return response()->json([
                'message' => 'Dữ liệu không hợp lệ',
                'errors' => [
                    'total_penalty_points' => ['Tổng điểm phạt không được âm (phải >= 0).']
                ]
            ], 422);
        }

        // Tương tự cho semester (nhưng rule in:1,2 đã handle, if này redundant)
        if (isset($validated['semester']) && !in_array($validated['semester'], [1, 2])) {
            return response()->json([
                'message' => 'Dữ liệu không hợp lệ',
                'errors' => [
                    'semester' => ['Học kỳ phải là 1 (Học kỳ 1) hoặc 2 (Học kỳ 2).']
                ]
            ], 422);
        }
        try {
            $conductScore = $this->conductScoreService->createConductScore($validated);

            return response()->json([
                'message' => 'Conduct score created successfully',
                'data' => new ConductScoreResource($conductScore)
            ], 201);
        } catch (\Exception $e) {
            dd($e);
            Log::error('Error creating conduct score', [
                'data' => $validated,
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'message' => 'Error creating conduct score',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * PUT /api/conduct-scores/{conductScore} - Cập nhật điểm hạnh kiểm
     * Permissions: teacher (homeroom)
     */
    public function update(Request $request, int $conductScoreId): JsonResponse
    {
        $validated = $request->validate([
            'teacher_comment' => 'sometimes|string|max:1000',
            'total_penalty_points' => 'sometimes|integer'
        ]);


        // Explicit check for total_penalty_points after validation (redundant but for custom error)
        if (isset($validated['total_penalty_points']) && $validated['total_penalty_points'] < 0) {
            return response()->json([
                'message' => 'Error updating conduct score',
                'error' => 'Tổng điểm phạt không được âm (phải >= 0).'
            ], 422); // Custom 422 Unprocessable Entity
        }

        try {
            // Load existing conduct score to extract composite keys
            $existing = \App\Models\StudentConductScore::findOrFail($conductScoreId);

            $conductScore = $this->conductScoreService->updateConductScore(
                $existing->student_id,
                (int) $existing->semester,
                (int) $existing->academic_year_id,
                $validated
            );

            return response()->json([
                'message' => 'Conduct score updated successfully',
                'data' => new ConductScoreResource($conductScore)
            ], 200);
        } catch (\Exception $e) {
            Log::error('Error updating conduct score', [
                'conduct_score_id' => $conductScoreId,
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'message' => 'Error updating conduct score',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * POST /api/conduct-scores/{conductScore}/approve - Phê duyệt điểm hạnh kiểm
     * Permissions: admin, principal
     */
    public function approve(Request $request, int $conductScoreId): JsonResponse
    {

        try {
            $conductScoreModel = \App\Models\StudentConductScore::findOrFail($conductScoreId);
            $conductScore = $this->conductScoreService->approveConductScore(
                $conductScoreModel->id
            );
            // dd($conductScore);

            return response()->json([
                'message' => 'Conduct score approved successfully',
                'data' => new ConductScoreResource($conductScore)
            ], 200);
        } catch (\Exception $e) {
            Log::error('Error approving conduct score', [
                'conduct_score_id' => $conductScoreId,
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'message' => 'Error approving conduct score',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * POST /api/conduct-scores/recalculate - Tính lại điểm hạnh kiểm và thống kê báo cáo kỷ luật
     * Permissions: admin, principal
     */
    public function recalculate(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'semester' => 'required|integer|in:1,2',
            'academic_year_id' => 'required|exists:academic_years,id',
            'class_id' => 'nullable|exists:classes,id',
            'student_id' => 'nullable|exists:students,id'
        ]);

        try {
            $report = $this->conductScoreService->recalculateConductScores(
                $validated['semester'],
                $validated['academic_year_id'],
                $validated['class_id'] ?? null,
                $validated['student_id'] ?? null
            );

            return response()->json([
                'message' => 'Conduct scores recalculated and discipline report generated successfully',
                'data' => $report
            ], 200);
        } catch (\Exception $e) {
            Log::error('Error recalculating conduct scores', [
                'filters' => $validated,
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'message' => 'Error recalculating conduct scores',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}
