<?php

namespace App\Actions\Admin;

use App\Enums\AdminRole;
use App\Models\Admin;
use Illuminate\Support\Facades\Hash;

class CreateManagerAction
{
    public function handle(array $data)
    {
        $data['password'] = Hash::make($data['password']);
        $data['role'] = AdminRole::Manager;

        // Auto-create Stripe Connect Express account
        try {
            \Stripe\Stripe::setApiKey(config('services.stripe.secret'));
            $account = \Stripe\Account::create([
                'type' => 'express',
                'email' => $data['email'],
                'capabilities' => [
                    'card_payments' => ['requested' => true],
                    'transfers' => ['requested' => true],
                ],
            ]);
            $data['stripe_connect_account_id'] = $account->id;
            $data['stripe_onboarding_completed'] = false;
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::error('Failed to create Stripe Connect account during manager registration: ' . $e->getMessage());
        }

        return Admin::create($data);
    }
}
