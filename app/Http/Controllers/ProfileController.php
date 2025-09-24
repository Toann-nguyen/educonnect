<?php

namespace App\Http\Controllers;

use App\Http\Requests\ProfileUpdateRequest;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Redirect;
use Inertia\Inertia;
use Inertia\Response;
use App\Models\Student;
use App\Models\User;
use App\Models\Invoice;
use App\Models\Grade;

class ProfileController extends Controller
{
    public function index(Request $request)
    {
        $user = $request->user();
        $dashboardData = [];

        if ($user->hasRole('admin')) {
            $dashboardData = [
                'total_users' => User::count(),
                'total_students' => Student::count(),
                'pending_invoices' => Invoice::where('status', 'unpaid')->count(),
                'recent_activities' => $this->getRecentActivities()
            ];
        } elseif ($user->hasRole('teacher')) {
            $dashboardData = [
                'my_classes' => $user->teachingSchedules()->with('schoolClass')->distinct('class_id')->count(),
                'pending_grades' => $this->getPendingGrades($user),
                'recent_disciplines' => $user->reportedDisciplines()->latest()->limit(5)->get()
            ];
        } elseif ($user->hasRole('student')) {
            $student = $user->student;
            $dashboardData = [
                'my_grades' => $student?->grades()->with('subject')->latest()->limit(10)->get(),
                'my_attendance' => $student?->attendances()->latest()->limit(10)->get(),
                'pending_invoices' => $student?->invoices()->where('status', 'unpaid')->get()
            ];
        } elseif ($user->hasRole('parent')) {
            $children = $user->guardianStudents()->with('user.profile', 'schoolClass')->get();
            $dashboardData = [
                'children' => $children,
                'pending_invoices' => Invoice::whereIn('student_id', $children->pluck('id'))->where('status', 'unpaid')->get(),
                'recent_grades' => Grade::whereIn('student_id', $children->pluck('id'))->with('subject', 'student.user.profile')->latest()->limit(10)->get()
            ];
        }
        return response()->json([
            'user' => $user,
            'dashboard' => $dashboardData
        ]);
    }
}
