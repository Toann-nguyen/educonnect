<?php

namespace App\Http\Controllers;

use App\Services\Interface\DashBoardServiceInterface;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class DashBoardController extends Controller
{
    protected $dashboardService;
    public function __construct(DashBoardServiceInterface $dashBoardServiceInterface)
    {
        $this->dashboardService = $dashBoardServiceInterface;
    }

    public function index(Request $request)
    {

        try {
            $user = $request->user();
            $dashboardData = $this->dashboardService->getDataForUser($user);

            return response()->json([
                'message' => 'Dashboard data service successfully',
                'data' => $dashboardData,
            ]);
        } catch (Exception $e) {
            Log::error(
                'Fail to service dashboard data for user:' . ($request->user()->id ?? 'guest'),
                [
                    'error' => $e->getMessage(),
                    'trace' => $e->getTraceAsString(),
                ]
            );
        }
    }
}
