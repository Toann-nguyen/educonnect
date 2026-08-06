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
            ])->timeout(5)->get("{$this->baseUrl}/api/v1/students/{$studentId}/invoices");

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
            ])->timeout(10)->post("{$this->baseUrl}/api/v1/invoices", $data);

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
}