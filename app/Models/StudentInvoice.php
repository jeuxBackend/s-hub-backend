<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class StudentInvoice extends Model
{
    protected $fillable = [
        'student_id',
        'paid_by',
        'for_month',
        'for_year',
        'amount',
        'discount',
        'total_amount',
        'paid_amount',
        'due_amount',
        'status',
        'payment_date',
        'payment_method',
        'reference_no',
    ];

    public function student()
    {
        return $this->belongsTo(Student::class);
    }

    public function payer()
    {
        return $this->belongsTo(User::class, 'paid_by');
    }
}
