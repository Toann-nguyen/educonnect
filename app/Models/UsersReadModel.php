<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * UsersReadModel — read model user sync từ Identity qua RabbitMQ (user_events)
 * Không phải nguồn sự thật; chỉ phục vụ query nhanh (join, hiển thị tên, ...)
 */
class UsersReadModel extends Model
{
    protected $table = 'users_read_model';

    protected $fillable = [
        'id', 'name', 'email', 'roles', 'is_active',
    ];

    protected $casts = [
        'roles'     => 'array',
        'is_active' => 'boolean',
    ];

    public $incrementing = false;

    protected $primaryKey = 'id';
}
