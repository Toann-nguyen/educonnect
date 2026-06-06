<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class LibraryBook extends Model
{
    use HasFactory;

    protected $fillable = ['title', 'author', 'total_quantity', 'available_quantity'];

    /** Lấy các giao dịch mượn/trả sách */
    public function transactions()
    {
        return $this->hasMany(LibraryTransaction::class, 'book_id');
    }

    /** Lấy các giao dịch đang mượn */
    public function activeBorrows()
    {
        return $this->hasMany(LibraryTransaction::class, 'book_id')->where('status', 'borrowed');
    }
}
