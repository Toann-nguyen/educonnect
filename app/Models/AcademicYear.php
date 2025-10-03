<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class AcademicYear extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'start_date',
        'end_date',
        'is_active'
    ];

    protected $casts = [
        'start_date' => 'date', // <-- THÊM DÒNG NÀY
        'end_date' => 'date',   // <-- THÊM DÒNG NÀY
        'is_active' => 'boolean',
    ];
}
