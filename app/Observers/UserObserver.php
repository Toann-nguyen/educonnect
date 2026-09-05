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
        // T5.1 + T6.1: auth revoke hybrid (jti+tv+sid) — tăng tv khi đổi pass/khóa/logout all + publish auth.events
        $authChanged = $user->wasChanged(['is_active', 'is_locked', 'status', 'password', 'password_hash']);
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

            // T5.1: đồng bộ tv lên Redis để Go check ngay (trước khi DB fallback)
            try {
                $newTv = (int) (DB::connection('identity')->table('users')->where('id', $user->id)->value('token_version') ?? $user->token_version + 1);
                app(\App\Domains\Identity\Services\Auth\TokenRevocationService::class)->setUserTv((int) $user->id, $newTv);
            } catch (\Throwable $e) {}

            // T6.1: publish auth.events (idempotency + outbox) for Go consumers to invalidate cache
            try {
                $newTv = (int) (DB::connection('identity')->table('users')->where('id', $user->id)->value('token_version') ?? $user->token_version + 1);
                if (!$user->is_active || $user->is_locked) {
                    \App\Services\AuthEventPublisher::userDeactivated($user->id, $user->is_locked ? 'locked' : 'deactivated');
                } else {
                    \App\Services\AuthEventPublisher::tokenVersionBumped($user->id, $newTv, 'status_changed');
                }
                \App\Services\AuthEventPublisher::permissionsChanged($user->id, [], 'observer');
            } catch (\Throwable $e) {
                Log::warning('AuthEventPublisher failed in UserObserver: ' . $e->getMessage());
            }
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
