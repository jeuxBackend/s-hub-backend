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
        $timezone = optional($this->teacher)->timezone ?: config('app.timezone', 'UTC');

        return [
            'id' => $this->id,
            'teacher_id' => $this->teacher_id,
            'subject_id' => $this->subject_id,
            'class_id' => $this->whenLoaded('subject', function () {
                return optional($this->subject->classroom)->id;
            }),
            'subject_name' => $this->whenLoaded('subject', fn() => $this->subject->name),
            'class_name' => $this->whenLoaded('subject', fn() => optional($this->subject->classroom)->name),
            'date' => $this->date,
            'status' => $this->status,
            'marked_at' => $this->created_at?->copy()->timezone($timezone)->format('Y-m-d H:i:s'),
            'checked_out_at' => $this->checked_out_at?->copy()->timezone($timezone)->format('Y-m-d H:i:s'),
            'timezone' => $timezone,
            'message' => $this->message,
            'created_at' => $this->created_at,
        ];
    }
}
