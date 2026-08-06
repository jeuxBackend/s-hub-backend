<?php

namespace App\Actions\Teacher;

use App\Models\Institution;
use App\Models\User;
use App\Enums\UserRole;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class CreateTeacherAction
{
    public function handle(array $data, int $institutionId, int $creatorId): User
    {
        $data['password'] = filled($data['password'] ?? null) ? $data['password'] : null;
        $data['role'] = UserRole::Teacher->value;
        $data['institution_id'] = $institutionId;
        $data['created_by'] = $creatorId;
        $data['status'] = true;
        $data['longitude'] = $data['longitude'] ?? null;
        $data['latitude'] = $data['latitude'] ?? null;
        $data['address'] = $data['address'] ?? null;
        $data['country'] = $data['country'] ?? null;
        $data['title'] = $data['title'] ?? null;
        $data['emergency_number'] = $data['emergency_number'] ?? null;
        $data['emergency_contact_name'] = $data['emergency_contact_name'] ?? null;

        Log::info('data: ', [$data]);
        // $data['position'] = $data['position'] ;

        if (isset($data['profile_picture']) && $data['profile_picture'] instanceof \Illuminate\Http\UploadedFile) {
            $data['profile_picture'] = $data['profile_picture']->store('profile_pictures', 'public');
        }

        unset($data['staff_number']);

        $teacher = User::create($data);

        $teacher->staff_number = $this->generateStaffNumber($teacher->id, $institutionId);
        $teacher->save();

        return $teacher->fresh();
    }

    private function generateStaffNumber(int $teacherId, int $institutionId): string
    {
        $institution = Institution::findOrFail($institutionId);
        $yearMonth = now()->format('ym');
        $initials = $this->getInstitutionInitials($institution->name);

        return "{$yearMonth}TEACHER{$teacherId}{$initials}";
    }

    private function getInstitutionInitials(string $institutionName): string
    {
        $initials = collect(preg_split('/\s+/', trim($institutionName)) ?: [])
            ->filter()
            ->map(fn (string $word) => Str::upper(Str::substr(preg_replace('/[^A-Za-z0-9]/', '', $word) ?? '', 0, 1)))
            ->filter()
            ->implode('');

        return $initials !== '' ? $initials : 'NA';
    }
}
