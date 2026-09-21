<?php

namespace App\Domains\School\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * UsersReadModel — read model user sync từ Identity qua RabbitMQ (user_events)
 * Lưu trong school_db.users_read_model, không phải nguồn sự thật; chỉ phục vụ query nhanh.
 */
class UsersReadModel extends Model
{
    protected $connection = 'school';

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
