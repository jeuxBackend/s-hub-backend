<?php

namespace App\Actions\Auth;

use App\Models\User;
use App\Models\Otp;
use App\Enums\OtpType;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

class SendOtpAction
{
    public function handle(array $data): array
    {
        $otpType = OtpType::from($data['type']);

        // Get user by email or phone
        $user = match ($otpType) {
            OtpType::Email => User::where('email', $data['email'])->firstOrFail(),
            OtpType::Phone => User::where('phone_number', $data['phone_number'])->firstOrFail(),
        };

        // Expire old OTPs of the same type
        Otp::where('user_id', $user->id)
            ->where('type', $otpType)
            ->where('verified', false)
            ->update(['expires_at' => now()]);

        // Generate OTP
        $code = (string) random_int(100000, 999999);

        // Store hashed OTP
        Otp::create([
            'user_id'    => $user->id,
            'code'       => $code,
            'type'       => $otpType,
            'verified'   => false,
            'expires_at' => now()->addMinutes(10),
        ]);

        // ✉️ Send OTP via email (inline, no Blade)
        if ($otpType === OtpType::Email) {
            Mail::raw("Your OTP code is: $code. It will expire in 10 minutes.", function ($message) use ($user) {
                $message->to($user->email)
                        ->subject('Your OTP Code');
            });
        }

        // 📱 Optionally send OTP via SMS in future
        // if ($otpType === OtpType::Phone) {
        //     SmsService::send($user->phone_number, "Your OTP is: $code");
        // }

        // 🧪 Return raw OTP only in non-production environments
        return app()->environment('local', 'testing') ? ['code' => $code] : [];
    }
}
