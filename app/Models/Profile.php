<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Profile extends Model
{
    use HasFactory;

    public $timestamps = false; // Thường profile không cần timestamps riêng
    protected $fillable = ['user_id', 'full_name', 'phone_number', 'birthday', 'gender', 'address', 'avatar'];

    /** Mối quan hệ N-1 với User */
    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
