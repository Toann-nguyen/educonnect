<?php

namespace App\Services;

use DB;
use Illuminate\Http\Request;

class ActivityLogServices
{
    public function log(int $userId, string $action, string $description, Request $request, array $metadata = []): void
    {
        $data = [
            'user_id' => $userId,
            'action' => $action,
            'description' => $description,
            'metadata' => $metadata
        ];

        if ($request) {
            $data['ip_address'] = $request->ip();
            $data['user_agent'] = $request->userAgent();
        }

        if (auth()->check()) {
            $data['performed_by'] = auth()->id();
        }
    }
}
