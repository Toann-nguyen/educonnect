<?php

namespace App\Domains\Identity\Models;

use Illuminate\Database\Eloquent\Model;

class OutboxEvent extends Model
{
    protected $connection = 'identity';

    protected $table = 'outbox_events';

    protected $fillable = [
        'aggregate_type', 'aggregate_id', 'event_type', 'payload', 'status', 'attempts', 'next_retry_at', 'published_at',
    ];

    protected $casts = [
        'payload' => 'array',
        'next_retry_at' => 'datetime',
        'published_at' => 'datetime',
    ];
}
