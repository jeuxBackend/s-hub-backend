<?php

namespace App\Actions\Parent;

use App\Models\StudentInvoice;
use Stripe\Stripe;
use Stripe\PaymentIntent;
use Exception;

class CreateParentPaymentIntentAction
{
    public function handle(int $parentId, int $invoiceId): array
    {
        $invoice = StudentInvoice::with(['student.institution.manager'])
            ->whereHas('student', function ($q) use ($parentId) {
                $q->where('guardian_id', $parentId);
            })
            ->findOrFail($invoiceId);

        if ($invoice->status === 'paid') {
            throw new Exception('This invoice has already been paid.', 400);
        }

        $student = $invoice->student;
        $institution = $student->institution;

        if (!$institution) {
            throw new Exception('The student is not associated with any school.', 400);
        }

        $manager = $institution->manager;

        if (!$manager || empty($manager->stripe_connect_account_id) || !$manager->stripe_onboarding_completed) {
            throw new Exception('This school is not set up to accept online card payments yet.', 400);
        }

        Stripe::setApiKey(config('services.stripe.secret'));

        $totalAmount = $invoice->total_amount;
        $amountInCents = intval(round($totalAmount * 100));

        // Deduct 2% platform fee (portal fee)
        $applicationFeeInCents = intval(round($totalAmount * 0.02 * 100));

        // Create the PaymentIntent using Stripe Connect Destination Charges
        $paymentIntent = PaymentIntent::create([
            'amount' => $amountInCents,
            'currency' => 'usd',
            'application_fee_amount' => $applicationFeeInCents,
            'transfer_data' => [
                'destination' => $manager->stripe_connect_account_id,
            ],
            'metadata' => [
                'invoice_id' => $invoice->id,
                'student_id' => $student->id,
                'guardian_id' => $parentId,
            ],
        ]);

        // Save the payment intent ID to the invoice
        $invoice->update([
            'stripe_payment_intent_id' => $paymentIntent->id,
        ]);

        return [
            'payment_intent_id' => $paymentIntent->id,
            'client_secret' => $paymentIntent->client_secret,
            'amount' => $totalAmount,
            'currency' => 'usd',
            'stripe_account_id' => $manager->stripe_connect_account_id,
        ];
    }
}
