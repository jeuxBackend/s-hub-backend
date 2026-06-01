<?php

namespace App\Http\Controllers\Api\Manager;

use App\Http\Controllers\Controller;
use App\Actions\Manager\CreateStripeConnectAccountAction;
use Illuminate\Http\Request;
use Stripe\Stripe;
use Stripe\Account;
use Throwable;

class StripeConnectController extends Controller
{
    /**
     * Initiate Stripe Connect Express Onboarding
     */
    public function connect(Request $request, CreateStripeConnectAccountAction $action)
    {
        $request->validate([
            'return_url' => ['required', 'url'],
            'refresh_url' => ['required', 'url'],
        ]);

        try {
            $manager = auth()->user(); // authenticated manager admin

            $onboardingUrl = $action->handle(
                $manager,
                $request->input('return_url'),
                $request->input('refresh_url')
            );

            return $this->successResponse([
                'onboarding_url' => $onboardingUrl
            ], 'Stripe onboarding URL generated successfully.');
        } catch (Throwable $e) {
            return $this->exceptionResponse($e);
        }
    }

    /**
     * Check and sync status of Stripe Connect Account
     */
    public function status()
    {
        try {
            $manager = auth()->user();

            if (empty($manager->stripe_connect_account_id)) {
                return $this->successResponse([
                    'stripe_connect_account_id' => null,
                    'stripe_onboarding_completed' => false,
                ], 'Stripe Connect Account not created yet.');
            }

            Stripe::setApiKey(config('services.stripe.secret'));
            $account = Account::retrieve($manager->stripe_connect_account_id);

            $onboardingCompleted = $account->details_submitted && $account->charges_enabled;

            if ($onboardingCompleted !== $manager->stripe_onboarding_completed) {
                $manager->update([
                    'stripe_onboarding_completed' => $onboardingCompleted
                ]);
            }

            return $this->successResponse([
                'stripe_connect_account_id' => $manager->stripe_connect_account_id,
                'stripe_onboarding_completed' => $manager->stripe_onboarding_completed,
                'payouts_enabled' => $account->payouts_enabled,
                'charges_enabled' => $account->charges_enabled,
            ], 'Stripe status retrieved successfully.');
        } catch (Throwable $e) {
            return $this->exceptionResponse($e);
        }
    }
}
