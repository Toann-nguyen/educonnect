<?php

namespace App\Services;

use App\Models\AcademicYear;
use App\Models\Event;
use App\Models\Invoice;
use App\Models\Payment;
use App\Models\SchoolClass;
use App\Models\Student;
use App\Models\User;
use App\Services\Interface\DashBoardServiceInterface;
use Carbon\Carbon;

class DashBoardService implements DashBoardServiceInterface
{

    public function getDataForUser(User $user)
    {
        // Sử dụng match statement để gọi hàm tương ứng với vai trò
        return match ($user->roles->first()?->name) {
            'admin', 'principal' => $this->getAdminDashboardData(),
            'teacher' => $this->getTeacherDashboardData($user),
            'student' => $this->getStudentDashboardData($user->student),
            // Thêm các vai trò khác ở đây
            default => [],
        };
    }

    /**
     * Thu thập và tính toán dữ liệu cho Dashboard của Admin/Hiệu trưởng.
     *
     * @return array
     */
    private function getAdminDashboardData(): array
    {
        // Lấy năm học hiện tại để lọc dữ liệu
        $activeYear = AcademicYear::where('is_active', true)->first();

        // Sử dụng cache để lưu các kết quả tốn kém, ví dụ cache trong 10 phút
        $stats = cache()->remember('admin_dashboard_stats', 600, function () use ($activeYear) {
            return [
                'total_students' => Student::count(),
                'total_teachers' => User::role('teacher')->count(),
                'total_classes' => $activeYear ? SchoolClass::where('academic_year_id', $activeYear->id)->count() : 0,
                'total_parents' => User::role('parent')->count(),
            ];
        });

        // Lấy dữ liệu tài chính không nên cache quá lâu
        $financials = [
            'revenue_today' => Payment::whereDate('payment_date', today())->sum('amount_paid'),
            'revenue_this_month' => Payment::whereYear('payment_date', today()->year)
                ->whereMonth('payment_date', today()->month)
                ->sum('amount_paid'),
            'overdue_invoices' => Invoice::where('status', 'overdue')->orWhere(function ($query) {
                $query->where('status', 'unpaid')->where('due_date', '<', today());
            })->count(),
        ];

        // Lấy 5 sự kiện sắp diễn ra gần nhất
        $upcomingEvents = Event::where('date', '>=', now())
            ->orderBy('date', 'asc')
            ->limit(5)
            ->get(['id', 'title', 'date']);

        return [
            'stats' => $stats,
            'financials' => $financials,
            'upcoming_events' => $upcomingEvents,
        ];
    }

    private function getTeacherDashboardData(User $user): array
    {
        // ... (Logic cho giáo viên)
        return [];
    }

    private function getStudentDashboardData(?Student $student): array
    {
        // ... (Logic cho học sinh)
        return [];
    }
}
