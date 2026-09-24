<?php

namespace App\Domains\School\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Teacher extends Model
{
    protected $connection = 'school';

    use HasFactory;

    protected $fillable = ['user_id', 'teacher_code', 'department', 'subjects', 'hire_date'];

    protected $casts = [
        'subjects' => 'array',
        'hire_date' => 'date',
    ];
}
