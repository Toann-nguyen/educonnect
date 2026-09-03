<?php

namespace App\Domains\Finance\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class FinanceApiClient
{
    protected string $baseUrl;

    public function __construct()
    {
        $this->baseUrl = rtrim(env('FINANCE_SERVICE_URL', 'http://finance:8080'), '/');
    }

    public function getInvoicesByStudent(int $studentId): array
    {
        try {
            $response = Http::withHeaders([
                'Authorization' => request()->header('Authorization'),
                'Accept' => 'application/json',
            ])->timeout(5)->get("{$this->baseUrl}/api/finance/invoices", ['student_id' => $studentId]);

            if ($response->successful()) {
                return $response->json();
            }

            Log::warning('Finance API error', [
                'status' => $response->status(),
                'body' => $response->body(),
            ]);
            return [];
        } catch (\Exception $e) {
            Log::error('Finance API exception', ['message' => $e->getMessage()]);
            return [];
        }
    }

    public function createInvoice(array $data): array
    {
        try {
            $response = Http::withHeaders([
                'Authorization' => request()->header('Authorization'),
                'Accept' => 'application/json',
            ])->timeout(10)->post("{$this->baseUrl}/api/finance/invoices", $data);

            if ($response->successful()) {
                return $response->json();
            }

            Log::warning('Finance API create error', [
                'status' => $response->status(),
                'body' => $response->body(),
            ]);
            return [];
        } catch (\Exception $e) {
            Log::error('Finance API create exception', ['message' => $e->getMessage()]);
            return [];
        }
    }

    /**
     * Lấy thống kê tài chính cho dashboard từ Finance service.
     * Go service chưa có endpoint /stats → thử gọi /payments và /invoices để tính, fallback 0.
     */
    public function getDashboardFinancials(): array
    {
        try {
            $headers = [
                'Authorization' => request()->header('Authorization', ''),
                'Accept' => 'application/json',
            ];
            // Thử gọi finance stats nếu có, không thì fallback 0
            $response = Http::withHeaders($headers)->timeout(3)->get("{$this->baseUrl}/api/finance/stats");
            if ($response->successful()) {
                return $response->json();
            }
            // Fallback: chưa có endpoint stats → trả 0, không block dashboard
            return ['revenue_today' => 0, 'revenue_this_month' => 0, 'overdue_invoices' => 0];
        } catch (\Exception $e) {
            Log::warning('Finance dashboard stats failed', ['msg' => $e->getMessage()]);
            return ['revenue_today' => 0, 'revenue_this_month' => 0, 'overdue_invoices' => 0];
        }
    }
}
