<?php

namespace App\Domains\Identity\Events;

use App\Domains\Identity\Models\User;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class UserRegistered
{
    use Dispatchable, SerializesModels;

    /**
     * Tạo một instance event mới.
     *
     * @param \App\Models\User $user
     */
    public function __construct(
        public User $user
    ) {}
}
