<?php

namespace App\Actions\User;

use App\Models\User;
use App\Actions\Auth\SendOtpAction;
use Illuminate\Support\Facades\Auth;

class UpdateContactAction
{
    public function handle(array $data, User $user): array
    {
        $updates = [];

        // ✅ Check for email change
        if (isset($data['email']) && $data['email'] !== $user->email) {
            $updates['pending_email'] = $data['email'];
        }

        // ✅ Check for phone number change
        if (isset($data['phone_number']) && $data['phone_number'] !== $user->phone_number) {
            $updates['pending_phone_number'] = $data['phone_number'];
        }

        // ❌ Nothing to update
        if (empty($updates)) {
            abort(400, 'No changes detected for email or phone number.');
        }

        // ✅ Mark user as unverified
        $user->update(array_merge($updates, ['otp_verified' => false]));

        // ✅ Send OTP
        $otp = app(SendOtpAction::class)->handle([
            'type'         => 'email_or_sms',
            'email'        => $updates['pending_email'] ?? null,
            'phone_number' => $updates['pending_phone_number'] ?? null,
        ]);

        // ✅ Logout user (invalidate tokens)
        $user->tokens()->delete();

        // ✅ Response
        $response = ['message' => 'OTP sent to updated contact. Please verify to complete change.'];

        if (app()->environment(['local', 'testing']) && isset($otp['code'])) {
            $response['debug_otp'] = $otp['code'];
        }

        return $response;
    }
}
