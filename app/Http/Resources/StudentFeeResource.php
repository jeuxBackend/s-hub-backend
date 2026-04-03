<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class StudentFeeResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        // return parent::toArray($request);
         return [
            'id'           => $this->id,
            'student_id'   => $this->student_id,
            // 'term'         => $this->term->value,
            'tuition_fee'  => $this->tuition_fee,
            'uniform_fee'  => $this->uniform_fee,
            'meals'        => $this->meals,
            'books'        => $this->books,
            'others'       => $this->others,
            'paid'         => $this->paid,
            'due_date'     => $this->due_date?->toDateString(),
          'term' => $this->term instanceof \App\Enums\TermType ? $this->term->value : $this->term,
    'status' => $this->status instanceof \App\Enums\PaymentStatusType ? $this->status->value : $this->status,
        ];
    }
}
