<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Invoice extends Model
{
    use HasFactory;

    protected $fillable = ['student_id', 'title', 'amount', 'due_date', 'status'];

    /** Lấy thông tin học sinh của hóa đơn */
    public function student()
    {
        return $this->belongsTo(Student::class);
    }

    /** Lấy lịch sử thanh toán */
    public function payments()
    {
        return $this->hasMany(Payment::class);
    }
}
