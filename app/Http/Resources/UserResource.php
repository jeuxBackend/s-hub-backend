<?php

namespace App\Http\Resources;

use App\Enums\UserRole;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class UserResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $role = $this->role ?? UserRole::Parent;

        $common = [
            'id' => $this->id,
            'email' => $this->email,
            'phone_number' => $this->phone_number,
            'role' => $role->value,
            'role_label' => $role->name,
            'address' => $this->address,
            'longitude' => $this->longitude,
            'latitude' => $this->latitude,
            'country' => $this->country,

            'first_name' => $this->first_name,
            'sur_name' => $this->sur_name,
            'full_name' => trim("{$this->first_name} {$this->sur_name}"),

            'profile_picture' => $this->profile_picture,
            'created_by' => $this->creator?->full_name,
            'notifications_enabled' => (bool) $this->notifications_enabled,
            'status' => $this->status,
            'fcm_token' => $this->fcm_token,
        ];

        $roleFields = match ($role) {
            UserRole::Principal => [
                'title' => $this->title,
                'position' => $this->position,
                'staff_number' => $this->staff_number,
                'address' => $this->address,
                'permissions' => $this->permissions ?? [],
                'institution' => $this->whenLoaded('institution', function () {
                        return new InstitutionResource($this->institution);
                    }),
            ],

            UserRole::SchoolAdmin => [
                'staff_number' => $this->staff_number,
                'position' => $this->position,
                'permissions' => $this->permissions ?? [],
                'remote' => $this->remote_teaching,

                'institution' => $this->when(
                    $this->relationLoaded('creator') && $this->creator?->relationLoaded('institution'),
                    fn() => new InstitutionResource($this->creator->institution)
                ),
            ],

            UserRole::Teacher => [
                'staff_number' => $this->staff_number,
                'position' => $this->position,
                'permissions' => $this->permissions ?? [],
                'remote' => $this->remote_teaching,
            ],

            UserRole::Parent => [
                'guardian_type' => $this->guardian_type?->value,
                'guardian_name' => $this->guardian_name,
                'guardian_relation' => $this->guardian_relation,
                'guardian_phone_number' => $this->guardian_phone_number,
                'alternative_guardian_phone_number' => $this->alternative_guardian_phone_number,
                'children' => $this->whenLoaded('guardianStudents', function () {
                    return $this->guardianStudents->map(function ($student) {
                        $latestInvoice = $student->relationLoaded('studentInvoices')
                            ? $student->studentInvoices->sortByDesc('id')->first()
                            : null;

                        return [
                            'id' => $student->id,
                            'first_name' => $student->first_name,
                            'sur_name' => $student->sur_name,
                            'full_name' => trim($student->first_name . ' ' . $student->sur_name),
                            'profile_picture' => $student->profile_picture,
                            'student_phone_number' => $student->student_phone_number,
                            'gender' => $student->gender->value ?? null,
                            'dob' => $student->dob?->toDateString(),
                            'age' => $student->age,
                            'term' => $student->term->value ?? null,
                            'registration_number' => $student->registration_number,
                            'status' => $student->status,
                            'classroom' => $student->classroom ? [
                                'id' => $student->classroom->id,
                                'name' => $student->classroom->name,
                                'code' => $student->classroom->code,
                            ] : null,
                            'tuition_status' => $latestInvoice?->status,
                            'total_paid' => $student->relationLoaded('studentInvoices') ? $student->studentInvoices->sum('paid_amount') : 0,
                            'total_due' => $latestInvoice?->due_amount ?? 0,
                        ];
                    });
                }),
            ],

            default => [],
        };

        return array_merge($common, $roleFields);
    }
}
