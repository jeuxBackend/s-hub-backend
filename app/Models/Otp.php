<?php

namespace App\Models;

use App\Enums\OtpType;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Hash;

class Otp extends Model
{
    protected $fillable = [
        'user_id',
        'code',
        'type',
        'verified',
        'expires_at',
    ];

    protected $casts = [
        'verified' => 'boolean',
        'expires_at' => 'datetime',
        'type' => OtpType::class,
        'code' => 'hashed',
    ];

    // Relations
    public function user()
    {
        return $this->belongsTo(User::class);
    }

    // Helper to validate OTP
  public static function validateAndGet(int $userId, string $inputCode, OtpType $type): ?self
{
    $otp = self::where('user_id', $userId)
        ->where('type', $type)
        ->where('verified', false)
        ->where('expires_at', '>', now())
        ->latest()
        ->first();

    return $otp && Hash::check($inputCode, $otp->code) ? $otp : null;
}


    // Mark OTP as used
    public function markAsVerified(): void
    {
        $this->update(['verified' => true]);
    }
}
