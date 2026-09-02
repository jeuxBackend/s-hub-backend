<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ManagerInvoice extends Model
{
    use HasFactory;

    protected $fillable = [
        'manager_id',
        'created_by',
        'invoice_number',
        'number_of_instutes',
        'price_per_instute',
        'currency',
        'total_amount',
        'due_date',
        'status',
    ];

    public function manager()
    {
        return $this->belongsTo(Admin::class, 'manager_id');
    }

    public function creator()
    {
        return $this->belongsTo(Admin::class, 'created_by');
    }
}
