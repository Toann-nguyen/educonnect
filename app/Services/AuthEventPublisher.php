<?php

namespace App\Services;

use App\Domains\Identity\Models\OutboxEvent;
use App\Jobs\PublishAuthEventJob;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * AuthEventPublisher — T6.1 outbox pattern for auth.events
 *
 * Publish các sự kiện auth.* (role/permission/status/token_version) lên
 * exchange auth.events (topic, durable) với:
 *  - idempotency_key (message_id + header x-idempotency-key)
 *  - correlation_id để trace
 *  - retry/DLQ do consumer + job backoff đảm nhiệm
 *
 * Outbox đảm bảo atomic: ghi outbox_events trong cùng transaction với thay đổi
 * domain, job PublishAuthEventJob sẽ publish sau commit (->afterCommit()).
 */
class AuthEventPublisher
{
    public const EXCHANGE = 'auth.events';

    /**
     * @param string $eventType  e.g. auth.role_assigned, auth.permissions_changed, auth.user_deactivated, auth.token_version_bumped
     * @param int|string $aggregateId user id hoặc role id
     * @param array $payload  business payload (sẽ được wrap với event/correlation_id/occurred_at/idempotency_key)
     * @param string|null $idempotencyKey  nếu null tự sinh uuid
     */
    public static function publish(string $eventType, int|string $aggregateId, array $payload = [], ?string $idempotencyKey = null): ?OutboxEvent
    {
        $idempotencyKey ??= (string) Str::uuid();
        $correlationId = $payload['correlation_id'] ?? (string) Str::uuid();

        $envelope = [
            'event' => $eventType,
            'version' => 1,
            'correlation_id' => $correlationId,
            'idempotency_key' => $idempotencyKey,
            'occurred_at' => now()->toIso8601String(),
            'aggregate_id' => $aggregateId,
            'payload' => $payload,
        ];

        try {
            $outbox = OutboxEvent::create([
                'aggregate_type' => self::aggregateType($eventType),
                'aggregate_id' => (int) $aggregateId ?: 0,
                'event_type' => $eventType,
                'payload' => $envelope,
                'status' => 'pending',
            ]);

            // Lưu idempotency_key vào payload để consumer check (không cần cột riêng nếu chưa migrate)
            // Nếu cột idempotency_key tồn tại thì update riêng
            try {
                if (\Schema::connection('identity')->hasColumn('outbox_events', 'idempotency_key')) {
                    $outbox->update(['idempotency_key' => $idempotencyKey]);
                }
            } catch (\Throwable $e) {
                // ignore
            }

            PublishAuthEventJob::dispatch($outbox->id)->afterCommit();

            Log::info("[auth.outbox] enqueued {$eventType} aggregate={$aggregateId} idem={$idempotencyKey} corr={$correlationId} outbox={$outbox->id}");

            return $outbox;
        } catch (\Throwable $e) {
            Log::error("[auth.outbox] enqueue failed {$eventType}: " . $e->getMessage());
            return null;
        }
    }

    public static function roleAssigned(int $userId, string|array $roles, ?string $actorId = null): ?OutboxEvent
    {
        return self::publish('auth.role_assigned', $userId, [
            'user_id' => $userId,
            'roles' => (array) $roles,
            'actor_id' => $actorId,
        ]);
    }

    public static function roleRevoked(int $userId, string|array $roles, ?string $actorId = null): ?OutboxEvent
    {
        return self::publish('auth.role_revoked', $userId, [
            'user_id' => $userId,
            'roles' => (array) $roles,
            'actor_id' => $actorId,
        ]);
    }

    public static function permissionsChanged(int $userId, array $permissions = [], ?string $actorId = null): ?OutboxEvent
    {
        return self::publish('auth.permissions_changed', $userId, [
            'user_id' => $userId,
            'permissions' => $permissions,
            'actor_id' => $actorId,
        ]);
    }

    public static function userDeactivated(int $userId, ?string $reason = null): ?OutboxEvent
    {
        return self::publish('auth.user_deactivated', $userId, [
            'user_id' => $userId,
            'reason' => $reason,
        ]);
    }

    public static function userReactivated(int $userId): ?OutboxEvent
    {
        return self::publish('auth.user_reactivated', $userId, [
            'user_id' => $userId,
        ]);
    }

    public static function tokenVersionBumped(int $userId, int $newVersion, string $reason = 'logout_all'): ?OutboxEvent
    {
        return self::publish('auth.token_version_bumped', $userId, [
            'user_id' => $userId,
            'token_version' => $newVersion,
            'reason' => $reason,
        ]);
    }

    private static function aggregateType(string $eventType): string
    {
        if (str_starts_with($eventType, 'auth.role')) return 'role';
        if (str_starts_with($eventType, 'auth.permission')) return 'permission';
        return 'user';
    }
}
