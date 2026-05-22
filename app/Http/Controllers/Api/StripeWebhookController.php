<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\StudentInvoice;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Stripe\Stripe;
use Stripe\Webhook;
use Stripe\Event;
use Throwable;

class StripeWebhookController extends Controller
{
    /**
     * Handle incoming Stripe webhook requests
     */
    public function handleWebhook(Request $request)
    {
        $payload = $request->getContent();
        $sigHeader = $request->header('Stripe-Signature');
        $webhookSecret = config('services.stripe.webhook_secret');

        Stripe::setApiKey(config('services.stripe.secret'));

        $event = null;

        // 1. Verify Stripe Webhook signature (unless local dev without webhook secret)
        if ($webhookSecret) {
            try {
                $event = Webhook::constructEvent($payload, $sigHeader, $webhookSecret);
            } catch (Throwable $e) {
                Log::error('Stripe Webhook signature verification failed: ' . $e->getMessage());
                return response()->json(['error' => 'Signature verification failed: ' . $e->getMessage()], 400);
            }
        } else {
            if (app()->environment('local')) {
                // If local environment and no webhook secret, construct event without signature checking
                $data = json_decode($payload, true);
                if (isset($data['type'])) {
                    try {
                        $event = Event::constructFrom($data);
                    } catch (Throwable $e) {
                        Log::error('Stripe Event construction failed locally: ' . $e->getMessage());
                        return response()->json(['error' => 'Invalid event data'], 400);
                    }
                }
            } else {
                Log::error('Stripe Webhook secret is not configured in production.');
                return response()->json(['error' => 'Webhook secret not configured'], 400);
            }
        }

        if (!$event) {
            return response()->json(['error' => 'Event could not be parsed'], 400);
        }

        Log::info('Stripe Webhook Event Received: ' . $event->type);

        // 2. Process the event
        switch ($event->type) {
            case 'payment_intent.succeeded':
                $paymentIntent = $event->data->object;
                $this->handlePaymentIntentSucceeded($paymentIntent);
                break;
            default:
                Log::info('Unhandled Stripe Webhook Event: ' . $event->type);
                break;
        }

        return response()->json(['status' => 'success'], 200);
    }

    /**
     * Process payment_intent.succeeded
     */
    private function handlePaymentIntentSucceeded($paymentIntent)
    {
        $invoiceId = $paymentIntent->metadata->invoice_id ?? null;

        if (!$invoiceId) {
            Log::warning('Stripe PaymentIntent succeeded with no invoice_id in metadata. Intent ID: ' . $paymentIntent->id);
            return;
        }

        $invoice = StudentInvoice::find($invoiceId);

        if (!$invoice) {
            Log::error('Stripe Webhook: Student Invoice ID ' . $invoiceId . ' not found.');
            return;
        }

        if ($invoice->status !== 'paid') {
            $invoice->update([
                'status' => 'paid',
                'paid_amount' => $invoice->total_amount,
                'due_amount' => 0,
                'payment_date' => now(),
                'payment_method' => 'stripe',
                'reference_no' => $paymentIntent->id,
                'stripe_payment_intent_id' => $paymentIntent->id,
            ]);

            Log::info("Student Invoice ID {$invoiceId} marked as PAID via Stripe Webhook. PaymentIntent: {$paymentIntent->id}");
        } else {
            Log::info("Student Invoice ID {$invoiceId} was already marked as PAID.");
        }
    }
}
