<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class UserSession extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id', 'refresh_token_id', 'device_name',
        'ip_address', 'user_agent', 'last_active_at',
    ];

    public function refreshToken()
    {
        return $this->belongsTo(RefreshToken::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
