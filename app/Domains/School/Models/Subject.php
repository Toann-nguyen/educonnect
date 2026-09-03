<?php

namespace App\Domains\School\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Subject extends Model
{
    protected $connection = 'school';
    use HasFactory;

    protected $fillable = ['name', 'subject_code'];
}
