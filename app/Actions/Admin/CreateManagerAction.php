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

        // Auto-create a Stripe Connect account (Accounts v2 — v1 Account::create
        // is blocked on newer Stripe platform accounts with "We recommend
        // building your integration using Accounts v2"). Stripe requires
        // identity.country upfront to attach the merchant configuration, so
        // this is skipped (same as any other Stripe failure here) until the
        // manager has a country — the manager's own /stripe/connect call
        // self-heals this the same way it does a missing account id.
        if (!empty($data['country'])) {
            try {
                $stripe = new \Stripe\StripeClient([
                    'api_key' => config('services.stripe.secret'),
                    'stripe_version' => config('services.stripe.connect_api_version'),
                ]);
                $account = $stripe->v2->core->accounts->create([
                    'contact_email' => $data['email'],
                    'dashboard' => \Stripe\V2\Core\Account::DASHBOARD_EXPRESS,
                    'identity' => [
                        'country' => $data['country'],
                    ],
                    'defaults' => [
                        'responsibilities' => [
                            'fees_collector' => 'stripe',
                            'losses_collector' => 'stripe',
                        ],
                    ],
                    'configuration' => [
                        'merchant' => [
                            'capabilities' => [
                                'card_payments' => ['requested' => true],
                            ],
                        ],
                        'recipient' => [
                            'capabilities' => [
                                'stripe_balance' => [
                                    'stripe_transfers' => ['requested' => true],
                                ],
                            ],
                        ],
                    ],
                ]);
                $data['stripe_connect_account_id'] = $account->id;
                $data['stripe_onboarding_completed'] = false;
            } catch (\Throwable $e) {
                \Illuminate\Support\Facades\Log::error('Failed to create Stripe Connect account during manager registration: ' . $e->getMessage());
            }
        }

        return Admin::create($data);
    }
}
