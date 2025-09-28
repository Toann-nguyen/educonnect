<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreGradeRequest;
use App\Http\Requests\UpdateGradeRequest;
use App\Http\Resources\GradeResource;
use App\Models\Grade;
use App\Services\Interface\GradeServiceInterface;
use App\Services\Interface\StudentServiceInterface;
use Illuminate\Http\Request;

class GradeController extends Controller
{
    protected $gradeService;
    public function __construct(GradeServiceInterface $gradeService)
    {
        $this->gradeService = $gradeService;
        // Áp dụng middleware cho các hàm khác nếu cần
    }

    public function myGrades(Request $request)
    {
        $user = $request->user();
        $personalGradesData = $this->gradeService->getPersonalGrades($user);
        dd($personalGradesData);
        // Kiểm tra nếu không có dữ liệu trả về (ví dụ: user là student nhưng chưa có record student)
        if (is_null($personalGradesData) || empty($personalGradesData)) {
            return response()->json(['data' => []]); // Trả về mảng rỗng
        }

        // Xử lý dữ liệu trả về cho Student
        if ($request->user()->hasRole('student')) {
            return response()->json([
                'student_id' => $personalGradesData['student_id'],
                'student_name' => $personalGradesData['student_name'],
                'data' => GradeResource::collection($personalGradesData['grades']),
            ]);
        }

        // Xử lý dữ liệu trả về cho Parent
        if ($request->user()->hasRole('parent')) {
            $formattedData = [];
            foreach ($personalGradesData as $childData) {
                $formattedData[] = [
                    'student_id' => $childData['student_id'],
                    'student_name' => $childData['student_name'],
                    'data' => GradeResource::collection($childData['grades']),
                ];
            }
            return response()->json($formattedData);
        }

        return response()->json([]);
    }
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        //
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        //
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(StoreGradeRequest $request)
    {
        //
    }

    /**
     * Display the specified resource.
     */
    public function show(Grade $grade)
    {
        //
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(Grade $grade)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(UpdateGradeRequest $request, Grade $grade)
    {
        //
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Grade $grade)
    {
        //
    }
}
