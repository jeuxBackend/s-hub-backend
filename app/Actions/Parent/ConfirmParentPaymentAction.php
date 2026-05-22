<?php

namespace App\Actions\Parent;

use App\Models\StudentInvoice;
use Stripe\Stripe;
use Stripe\PaymentIntent;
use Exception;

class ConfirmParentPaymentAction
{
    public function handle(int $parentId, int $invoiceId, string $paymentIntentId): StudentInvoice
    {
        $invoice = StudentInvoice::whereHas('student', function ($q) use ($parentId) {
            $q->where('guardian_id', $parentId);
        })->findOrFail($invoiceId);

        Stripe::setApiKey(config('services.stripe.secret'));

        $paymentIntent = PaymentIntent::retrieve($paymentIntentId);

        if ($paymentIntent->status === 'succeeded') {
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
            }
            return $invoice;
        }

        throw new Exception("Payment status is: {$paymentIntent->status}. Cannot confirm payment.", 400);
    }
}
