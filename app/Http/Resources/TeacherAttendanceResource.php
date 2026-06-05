<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class TeacherAttendanceResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     */
    public function toArray($request): array
    {
        return [
            'id'          => $this->id,
            'teacher_id'  => $this->teacher_id,
            // subject id is directly stored on the attendance record
            'subject_id'  => $this->subject_id,
            // class id comes from the related subject (if loaded)
            'class_id'    => $this->whenLoaded('subject', function () {
                return optional($this->subject->classroom)->id;
            }),
            // subject name (if subject is loaded)
            'subject_name'=> $this->whenLoaded('subject', fn() => $this->subject->name),
            // class name (if classroom is loaded via subject)
            'class_name'  => $this->whenLoaded('subject', fn() => optional($this->subject->classroom)->name),
            'date'   => $this->date, // keep raw string or Carbon if already cast
            'status'      => $this->status,
        ];
    }
}
