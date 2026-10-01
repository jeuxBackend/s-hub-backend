<?php

namespace App\Actions\Manager;

use App\Models\Admin;
use Illuminate\Validation\ValidationException;
use Stripe\StripeClient;
use Stripe\V2\Core\Account;

class CreateStripeConnectAccountAction
{
    /**
     * Accounts v2 — v1 Account::create/AccountLink::create are blocked on
     * newer Stripe platform accounts with "We recommend building your
     * integration using Accounts v2".
     */
    public function handle(Admin $manager, string $returnUrl, string $refreshUrl): string
    {
        // Stripe requires identity.country upfront to attach the merchant
        // configuration. Admins now always set this when creating a manager;
        // this is just a safety net for any manager predating that policy.
        if (empty($manager->country)) {
            throw ValidationException::withMessages([
                'country' => ['Your country is required to set up Stripe. Please provide a 2-letter country code.'],
            ]);
        }

        $stripe = new StripeClient([
            'api_key' => config('services.stripe.secret'),
            'stripe_version' => config('services.stripe.connect_api_version'),
        ]);

        // 1. Create the Stripe Connect account if not already created
        if (empty($manager->stripe_connect_account_id)) {
            $account = $stripe->v2->core->accounts->create([
                'contact_email' => $manager->email,
                'dashboard' => Account::DASHBOARD_EXPRESS,
                'identity' => [
                    'country' => $manager->country,
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
                'metadata' => [
                    'manager_id' => (string) $manager->id,
                ],
            ]);

            $manager->update([
                'stripe_connect_account_id' => $account->id,
                'stripe_onboarding_completed' => false,
            ]);
        }

        // 2. Generate an Account Link for onboarding
        $accountLink = $stripe->v2->core->accountLinks->create([
            'account' => $manager->stripe_connect_account_id,
            'use_case' => [
                'type' => 'account_onboarding',
                'account_onboarding' => [
                    'refresh_url' => $refreshUrl,
                    'return_url' => $returnUrl,
                ],
            ],
        ]);

        return $accountLink->url;
    }
}
