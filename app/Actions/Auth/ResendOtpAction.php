<?php

namespace App\Actions\Auth;

use App\Models\User;
use App\Models\Otp;
use App\Enums\OtpType;
use App\Actions\Auth\SendOtpAction;
use Illuminate\Auth\Access\AuthorizationException;

class ResendOtpAction
{
    public function handle(array $data): array
    {
        $otpType = OtpType::from($data['type']);

        // Retrieve user by type
        $user = match ($otpType) {
            OtpType::Email => User::where('email', $data['email'])->firstOrFail(),
            OtpType::Phone => User::where('phone_number', $data['phone_number'])->firstOrFail(),
        };

        // Prevent resending if a valid OTP already exists
        $existingOtp = Otp::where('user_id', $user->id)
            ->where('type', $otpType)
            ->where('verified', false)
            ->where('expires_at', '>', now())
            ->latest()
            ->first();

        if ($existingOtp) {
            throw new AuthorizationException('A valid OTP already exists. Please wait before resending.');
        }

        // Reuse SendOtpAction (includes mailing, SMS-ready, env checks)
        return app(SendOtpAction::class)->handle([
            'type'         => $data['type'],
            'email'        => $data['email'] ?? null,
            'phone_number' => $data['phone_number'] ?? null,
        ]);
    }
}
