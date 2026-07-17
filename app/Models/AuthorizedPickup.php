<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class AuthorizedPickup extends Model
{
    use HasFactory;

    protected $fillable = [
        'parent_id',
        'name',
        'phone_number',
        'address',
    ];

    public function parent()
    {
        return $this->belongsTo(User::class, 'parent_id');
    }
}
