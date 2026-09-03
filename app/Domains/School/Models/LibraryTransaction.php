<?php

namespace App\Domains\School\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class LibraryTransaction extends Model
{
    protected $connection = 'school';
    use HasFactory;

    protected $fillable = [
        'book_id',
        'user_id',
        'borrow_date',
        'due_date',
        'return_date',
        'status'
    ];

    protected $casts = [
        'borrow_date' => 'date',
        'due_date' => 'date',
        'return_date' => 'date',
    ];

    /** Lấy sách */
    public function book()
    {
        return $this->belongsTo(LibraryBook::class, 'book_id');
    }

    /** Lấy người mượn */
    public function user()
    {
    }
}
