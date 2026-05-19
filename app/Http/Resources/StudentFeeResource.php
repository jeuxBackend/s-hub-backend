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
            'student'      => $this->whenLoaded('student', function () {
                return [
                    'id' => $this->student->id,
                    'name' => trim($this->student->first_name . ' ' . $this->student->sur_name),
                    'profile_picture' => $this->student->profile_picture,
                    'registration_number' => $this->student->registration_number,
                ];
            }),
            'classroom'    => $this->whenLoaded('classroom', function () {
                return [
                    'id' => $this->classroom->id,
                    'name' => $this->classroom->name,
                ];
            }),
            // Map DB columns to old API keys
            'tuition_fee'  => $this->tuition_fee,
            'uniform_fee'  => $this->uniform_fee,
            'meals'        => $this->meals_fee,
            'books'        => $this->books_fee,
            'others'       => $this->other_fee,
            'paid'         => $this->paid_amount,
            'total_amount' => $this->total_amount,
            'due_date'     => $this->due_date?->toDateString(),
            'term'         => $this->class_id, // Mapping the new 'class_id' back to 'term' for the API
            'status'       => $this->status instanceof \App\Enums\PaymentStatusType ? $this->status->value : $this->status,
        ];
    }
}
