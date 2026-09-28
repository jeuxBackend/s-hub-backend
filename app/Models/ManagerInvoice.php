<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ManagerInvoice extends Model
{
    use HasFactory;

    protected $fillable = [
        'manager_id',
        'institution_id',
        'created_by',
        'invoice_number',
        'number_of_instutes',
        'price_per_instute',
        'currency',
        'total_amount',
        'due_date',
        'status',
        'payment_note',
        'payment_method',
        'payment_proof',
        'paid_submitted_at',
        'confirmed_by',
        'confirmed_at',
    ];

    protected $casts = [
        'paid_submitted_at' => 'datetime',
        'confirmed_at' => 'datetime',
    ];

    public function manager()
    {
        return $this->belongsTo(Admin::class, 'manager_id');
    }

    public function institution()
    {
        return $this->belongsTo(Institution::class, 'institution_id');
    }

    public function creator()
    {
        return $this->belongsTo(Admin::class, 'created_by');
    }

    public function confirmedBy()
    {
        return $this->belongsTo(Admin::class, 'confirmed_by');
    }
}
