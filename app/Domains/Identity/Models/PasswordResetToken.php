<?php

namespace App\Domains\Identity\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PasswordResetToken extends Model
{
    protected $connection = 'identity';
    use HasFactory;
}
