<?php

namespace App\Actions\Manager;

use App\Models\Admin;
use Stripe\Stripe;
use Stripe\Account;
use Stripe\AccountLink;
use Exception;

class CreateStripeConnectAccountAction
{
    public function handle(Admin $manager, string $returnUrl, string $refreshUrl): string
    {
        Stripe::setApiKey(config('services.stripe.secret'));

        // 1. Create Stripe Express Account if not already created
        if (empty($manager->stripe_connect_account_id)) {
            $account = Account::create([
                'type' => 'express',
                'email' => $manager->email,
                'capabilities' => [
                    'card_payments' => ['requested' => true],
                    'transfers' => ['requested' => true],
                ],
                'metadata' => [
                    'manager_id' => $manager->id,
                ],
            ]);

            $manager->update([
                'stripe_connect_account_id' => $account->id,
                'stripe_onboarding_completed' => false,
            ]);
        }

        // 2. Generate Account Link for Onboarding
        $accountLink = AccountLink::create([
            'account' => $manager->stripe_connect_account_id,
            'refresh_url' => $refreshUrl,
            'return_url' => $returnUrl,
            'type' => 'account_onboarding',
        ]);

        return $accountLink->url;
    }
}
