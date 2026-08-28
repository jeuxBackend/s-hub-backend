<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Builder;

class SchoolAlert extends Model
{
    public const ACTIVE_COUNT_WINDOW_MINUTES = 30;

    public const ABDUCTION_EXPIRY_MINUTES = 15;

    protected $fillable = [
        'institution_id',
        'created_by',
        'confirmed_by',
        'resolved_by',
        'type',
        'status',
        'title',
        'message',
        'confirmation_count',
        'confirmed_at',
        'resolved_at',
        'meta',
    ];

    protected $casts = [
        'meta' => 'array',
        'confirmed_at' => 'datetime',
        'resolved_at' => 'datetime',
    ];

    public function institution()
    {
        return $this->belongsTo(Institution::class);
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function confirmedBy()
    {
        return $this->belongsTo(User::class, 'confirmed_by');
    }

    public function resolvedBy()
    {
        return $this->belongsTo(User::class, 'resolved_by');
    }

    public function responses()
    {
        return $this->hasMany(SchoolAlertResponse::class);
    }

    public function scopeWithinActiveCountWindow(Builder $query): Builder
    {
        return $query->where('created_at', '>=', now()->subMinutes(self::ACTIVE_COUNT_WINDOW_MINUTES));
    }

    /**
     * Excludes abduction alerts that have passed the abduction expiry window
     * without being resolved (mirrors SchoolAlertService::isExpiredForCurrentView).
     */
    public function scopeExcludingExpiredAbduction(Builder $query): Builder
    {
        return $query->where(function (Builder $q) {
            $q->where('type', '!=', 'abduction')
                ->orWhere('status', 'resolved')
                ->orWhere('created_at', '>', now()->subMinutes(self::ABDUCTION_EXPIRY_MINUTES));
        });
    }
}
