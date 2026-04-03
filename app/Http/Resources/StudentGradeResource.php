<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class StudentGradeResource extends JsonResource
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
            'student'      => new StudentResource($this->student),
            'subject'      => new SubjectResource($this->subject),
            'term'         => $this->term->value,
            'score'        => $this->score,
            'remarks'      => $this->remarks,
            'type'         => $this->type,
           'recorded_by' => new UserResource($this->recordedBy),
            'date'        => $this->date,
        ];
    }
}
