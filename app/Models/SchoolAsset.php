<?php

namespace App\Models;

use App\Enums\SchoolAssetFormat;
use App\Enums\SchoolAssetType;
use Illuminate\Database\Eloquent\Model;

class SchoolAsset extends Model
{
    protected $fillable = [
        'institution_id',
        'type',
        'asset_format',
        'text_value',
        'file_path',
        'created_by',
    ];

    protected $casts = [
        'type' => SchoolAssetType::class,
        'asset_format' => SchoolAssetFormat::class,
    ];

    public function institution()
    {
        return $this->belongsTo(Institution::class);
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function getFileUrlAttribute(): ?string
    {
        return $this->file_path ? asset('storage/' . $this->file_path) : null;
    }
}
