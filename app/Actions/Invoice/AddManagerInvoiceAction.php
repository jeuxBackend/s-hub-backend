<?php

namespace App\Actions\Invoice;

use App\Models\ManagerInvoice;

class AddManagerInvoiceAction
{
    public function handle(array $data): ManagerInvoice
    {
        return ManagerInvoice::create([
            'manager_id' => $data['manager_id'],
            'created_by' => $data['created_by'],
            'invoice_number' => $this->generateInvoiceNumber(),
            'number_of_instutes' => $data['number_of_instutes'],
            'price_per_instute' => $data['price_per_instute'],
            'currency' => $data['currency'] ?? 'USD',
            'total_amount' => $data['number_of_instutes'] * $data['price_per_instute'],
            'due_date' => $data['due_date'],
            'status' => $data['status'] ?? 'pending',
        ]);
    }

    private function generateInvoiceNumber(): string
    {
        do {
            $number = 'INV-' . now()->format('Ymd') . '-' . strtoupper(substr(bin2hex(random_bytes(3)), 0, 6));
        } while (ManagerInvoice::where('invoice_number', $number)->exists());

        return $number;
    }
}
