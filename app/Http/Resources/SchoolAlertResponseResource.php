<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class SchoolAlertResponseResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'school_alert_id' => $this->school_alert_id,
            'institution_id' => $this->institution_id,
            'user_id' => $this->user_id,
            'parent_user_id' => $this->parent_user_id,
            'school_user_id' => $this->school_user_id,
            'student_id' => $this->student_id,
            'source_role' => $this->source_role,
            'parent_response_type' => $this->parent_response_type,
            'school_response_type' => $this->school_response_type,
            'note' => $this->note,
            'meta' => $this->meta,
            'responded_at' => $this->responded_at?->toDateTimeString(),
            'created_at' => $this->created_at?->toDateTimeString(),
            'updated_at' => $this->updated_at?->toDateTimeString(),
            'student_name' => $this->student?->full_name,
            'student_profile_picture' => $this->student?->profile_picture,
            'student_classroom' => $this->whenLoaded('student', function () {
                return $this->student?->classroom ? [
                    'id' => $this->student->classroom->id,
                    'name' => $this->student->classroom->name,
                    'code' => $this->student->classroom->code,
                ] : null;
            }),
            'parent' => $this->whenLoaded('parentUser', function () {
                return $this->parentUser ? [
                    'id' => $this->parentUser->id,
                    'name' => $this->parentUser->full_name,
                    'role' => $this->parentUser->role?->value ?? $this->parentUser->role,
                    'profile_picture' => $this->parentUser->profile_picture,
                ] : null;
            }),
            'school_staff' => $this->whenLoaded('schoolUser', function () {
                return $this->schoolUser ? [
                    'id' => $this->schoolUser->id,
                    'name' => $this->schoolUser->full_name,
                    'role' => $this->schoolUser->role?->value ?? $this->schoolUser->role,
                    'profile_picture' => $this->schoolUser->profile_picture,
                ] : null;
            }),
            'user' => $this->whenLoaded('user', fn () => new UserResource($this->user)),
            'student' => $this->whenLoaded('student', fn () => new StudentResource($this->student)),
            'alert' => $this->whenLoaded('alert', function () {
                return [
                    'id' => $this->alert->id,
                    'type' => $this->alert->type,
                    'status' => $this->alert->status,
                    'title' => $this->alert->title,
                    'message' => $this->alert->message,
                ];
            }),
        ];
    }
}
