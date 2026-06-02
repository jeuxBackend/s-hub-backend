<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class StudentFee extends Model
{
    use HasFactory;

    protected $fillable = [
        'student_id',
        'class_id',
        'tuition_fee',
        'uniform_fee',
        'meals_fee',
        'books_fee',
        'other_fee',
        'paid_amount',
        'total_amount',
        'due_date',
        'paid_date',
        'payment_month',
        'status',
    ];
    protected static function booted()
    {
        static::saving(function ($fee) {
            if ($fee->paid_amount === null) {
                $fee->status = null;
                return;
            }

            $total = (
                ($fee->tuition_fee ?? 0) +
                ($fee->uniform_fee ?? 0) +
                ($fee->meals_fee ?? 0) +
                ($fee->books_fee ?? 0) +
                ($fee->other_fee ?? 0));

            if ($fee->paid_amount >= $total) {
                $fee->status = 'paid';
            } elseif ($fee->paid_amount > 0) {
                $fee->status = 'partial';
            } else {
                $fee->status = 'unpaid';
            }
        });
    }

    protected $casts = [
        'tuition_fee' => 'float',
        'uniform_fee' => 'float',
        'meals_fee' => 'float',
        'books_fee' => 'float',
        'other_fee' => 'float',
        'paid_amount' => 'float',
        'due_date' => 'date',
        'paid_date' => 'date',
    ];

    // 🔁 Relationships
    public function student()
    {
        return $this->belongsTo(Student::class);
    }

    public function classroom()
    {
        return $this->belongsTo(Classroom::class, 'class_id');
    }

    // 🧮 Accessors
    public function getTotalDueAttribute(): float
    {
        return (
            ($this->tuition_fee ?? 0) +
            ($this->uniform_fee ?? 0) +
            ($this->meals_fee ?? 0) +
            ($this->books_fee ?? 0) +
            ($this->other_fee ?? 0)
        ) - ($this->paid_amount ?? 0);
    }
}
