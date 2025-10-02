<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Student extends Model
{
    use HasFactory;

    protected $fillable = ['user_id', 'class_id', 'student_code', 'status'];


    /** Lấy tài khoản user của học sinh */
    public function user()
    {
        return $this->belongsTo(User::class);
    }

    /** Lấy lớp học */
    public function schoolClass()
    {
        return $this->belongsTo(SchoolClass::class, 'class_id');
    }

    /** Lấy danh sách người giám hộ */
    public function guardians()
    {
        return $this->hasMany(StudentGuardian::class);
    }

    /** THÊM: Lấy điểm số của học sinh */
    public function grades()
    {
        return $this->hasMany(Grade::class);
    }

    /** THÊM: Lấy bản điểm danh */
    public function attendances()
    {
        return $this->hasMany(Attendance::class);
    }

    /** THÊM: Lấy hóa đơn của học sinh */
    public function invoices()
    {
        return $this->hasMany(Invoice::class);
    }

    /** THÊM: Lấy vi phạm kỷ luật */
    public function disciplines()
    {
        return $this->hasMany(Discipline::class);
    }

    /** THÊM: Lấy đăng ký sự kiện */
    public function eventRegistrations()
    {
        return $this->hasMany(EventRegistration::class);
    }

    /** THÊM: Lấy điểm hạnh kiểm */
    public function conductScores()
    {
        return $this->hasMany(StudentConductScore::class);
    }

    /** THÊM: Lấy điểm hạnh kiểm theo học kỳ và năm học */
    public function getConductScore($semester, $academicYearId)
    {
        return $this->conductScores()
            ->where('semester', $semester)
            ->where('academic_year_id', $academicYearId)
            ->first();
    }

    /** THÊM: Lấy tổng điểm trừ trong học kỳ hiện tại */
    public function getCurrentSemesterPenaltyPoints()
    {
        $activeYear = \App\Models\AcademicYear::where('is_active', true)->first();
        if (!$activeYear) return 0;

        $currentSemester = now() < $activeYear->start_date->copy()->addMonths(5) ? 1 : 2;

        $conductScore = $this->getConductScore($currentSemester, $activeYear->id);
        return $conductScore ? $conductScore->total_penalty_points : 0;
    }
}
