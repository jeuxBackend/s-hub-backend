<?php

namespace App\Http\Resources;

use App\Support\TimetableEntryResolver;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class TimetableEntryResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $resolver = app(TimetableEntryResolver::class);

        return [
            'id' => $this->id,
            'weekday' => (int) $this->weekday,
            'weekday_name' => $resolver->weekdayName((int) $this->weekday),
            'start_time' => date('h:i a', strtotime($this->start_time)),
            'end_time' => date('h:i a', strtotime($this->end_time)),
            'subject' => [
                'id' => $this->subject?->id ?? $this->subject_id,
                'name' => $this->subject?->name ?? $this->subject_name,
                'lectures_per_week' => (int) ($this->subject?->lectures_per_week ?? $this->lectures_per_week ?? 1),
            ],
            'teacher' => $this->whenLoaded('teacher', fn() => new UserResource($this->teacher), [
                'id' => $this->teacher?->id ?? $this->teacher_id,
                'full_name' => $this->teacher?->full_name ?? $this->teacher_name,
            ]),
            'classroom' => $this->whenLoaded('classroom', fn() => new ClassroomResource($this->classroom), [
                'id' => $this->classroom?->id ?? $this->classroom_id,
                'name' => $this->classroom?->name ?? $this->classroom_name,
            ]),
        ];
    }
}
