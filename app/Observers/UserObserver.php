<?php

namespace App\Observers;

use App\Domains\Identity\Models\OutboxEvent;
use App\Domains\Identity\Models\User;
use App\Domains\Identity\Services\PermissionCacheService;
use App\Jobs\PublishUserEventJob;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class UserObserver
{
    public function created(User $user): void
    {
        $this->enqueue($user, 'user.created');
    }

    public function updated(User $user): void
    {
        // Preserve existing cache clear + token_version bump for auth-relevant fields
        $authChanged = $user->wasChanged(['is_active', 'is_locked', 'status']);
        if ($authChanged) {
            try {
                app(PermissionCacheService::class)->clearUser($user->id);
            } catch (\Throwable $e) {
                Log::warning('PermissionCache clear failed: ' . $e->getMessage());
            }

            // Tăng token_version bằng raw query để KHÔNG kích hoạt lại observer 'updated'
            DB::connection('identity')->table('users')
                ->where('id', $user->id)
                ->update(['token_version' => DB::raw('token_version + 1')]);
        }

        // Outbox publish only when meaningful fields changed (avoid noise)
        if (!$user->wasChanged(['name', 'email', 'is_active', 'is_locked', 'status', 'phone'])) {
            return;
        }

        $this->enqueue($user, 'user.updated');
    }

    public function deleted(User $user): void
    {
        $this->enqueue($user, 'user.deleted');
    }

    private function enqueue(User $user, string $eventType): void
    {
        try {
            $roles = [];
            try {
                $roles = $user->roles->pluck('name')->toArray();
            } catch (\Throwable $e) {
                $roles = [];
            }

            $payload = [
                'event' => $eventType,
                'correlation_id' => (string) Str::uuid(),
                'occurred_at' => now()->toIso8601String(),
                'user' => [
                    'id' => $user->id,
                    'name' => $user->name,
                    'email' => $user->email,
                    'roles' => $roles,
                    'is_active' => (bool) $user->is_active,
                    'is_locked' => (bool) ($user->is_locked ?? false),
                    'status' => $user->status ?? null,
                ],
            ];

            $outbox = OutboxEvent::create([
                'aggregate_type' => 'user',
                'aggregate_id' => $user->id,
                'event_type' => $eventType,
                'payload' => $payload,
                'status' => 'pending',
            ]);

            PublishUserEventJob::dispatch($outbox->id)->afterCommit();
        } catch (\Throwable $e) {
            Log::error('UserObserver enqueue failed: ' . $e->getMessage());
        }
    }
}
