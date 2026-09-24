<?php

namespace App\Console\Commands;

use App\Domains\School\Models\UsersReadModel;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Throwable;

final class ProjectorReplay extends Command
{
    protected $signature = 'projector:replay
        {--aggregate=user : Aggregate to replay}
        {--from=0 : Minimum event version}
        {--write : Apply projections; without this flag the command is read-only}
        {--dry : Alias for read-only mode}
        {--force-production : Required to apply projections in production}';

    protected $description = 'Replay append-only identity events into the school user read model';

    public function handle(): int
    {
        $aggregate = (string) $this->option('aggregate');
        if ($aggregate !== 'user') {
            $this->error('Only the user aggregate is supported.');

            return self::FAILURE;
        }

        try {
            $write = (bool) $this->option('write') && ! $this->option('dry');
            if ($write && app()->environment('production') && ! $this->option('force-production')) {
                $this->error('Refusing to write projections in production without --force-production.');

                return self::FAILURE;
            }

            $projected = 0;
            $skipped = 0;
            DB::connection('identity')->table('event_store')
                ->where('aggregate_type', 'user')
                ->where('version', '>=', max(0, (int) $this->option('from')))
                ->orderBy('aggregate_id')
                ->orderBy('version')
                ->chunkById(200, function ($events) use ($write, &$projected, &$skipped) {
                    DB::connection('school')->transaction(function () use ($events, $write, &$projected, &$skipped) {
                        foreach ($events as $event) {
                            $payload = is_array($event->payload) ? $event->payload : json_decode((string) $event->payload, true);
                            $user = is_array($payload) ? ($payload['user'] ?? $payload) : [];
                            $userId = $user['id'] ?? $event->aggregate_id ?? null;
                            if ($userId === null) {
                                $skipped++;
                                Log::warning('projector:replay skipped event without user id', [
                                    'event_id' => $event->id ?? null,
                                    'event_type' => $event->event_type ?? null,
                                ]);

                                continue;
                            }

                            if ($write) {
                                UsersReadModel::updateOrCreate(
                                    ['id' => (int) $userId],
                                    [
                                        'name' => $user['name'] ?? '',
                                        'email' => $user['email'] ?? '',
                                        'roles' => $user['roles'] ?? [],
                                        'is_active' => ($event->event_type === 'user.deleted') ? false : ($user['is_active'] ?? true),
                                    ],
                                );
                            }
                            $projected++;
                        }
                    });
                });

            $this->info("Replayed {$projected} user events to ".($write ? 'school.users_read_model' : 'dry-run')."; skipped {$skipped}.");

            return self::SUCCESS;
        } catch (Throwable $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }
    }
}
