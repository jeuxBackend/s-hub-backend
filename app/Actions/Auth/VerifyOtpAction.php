<?php

namespace App\Actions\Auth;

use App\Models\Otp;
use App\Models\User;
use App\Enums\OtpType;
use Illuminate\Support\Facades\Hash;
use Illuminate\Auth\Access\AuthorizationException;

class VerifyOtpAction
{
    public function handle(array $data): User
    {
        $otpType = OtpType::from($data['type']);

        // Retrieve the user by email or phone
        $user = match ($otpType) {
            OtpType::Email => User::where('email', $data['email'])->firstOrFail(),
            OtpType::Phone => User::where('phone_number', $data['phone_number'])->firstOrFail(),
        };

        // Get latest valid OTP for this user and type
        $otp = Otp::where('user_id', $user->id)
            ->where('type', $otpType)
            ->where('verified', false)
            ->where('expires_at', '>', now())
            ->latest()
            ->first();

        // Validate OTP code
        if (! $otp || ! Hash::check($data['otp_code'], $otp->code)) {
            throw new AuthorizationException('OTP is invalid or expired.');
        }

        // Mark OTP as verified
        $otp->markAsVerified();

        // Update user verification flags
        $user->update([
            'otp_verified' => true,
            'email_verified_at' => now(),
        ]);

      return $user->load('institution', 'creator');
    }
}
