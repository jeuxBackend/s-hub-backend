<?php

namespace App\Actions\Teacher;

use App\Models\User;
use App\Enums\UserRole;
use Illuminate\Support\Facades\Hash;

class CreateTeacherAction
{
    public function handle(array $data, int $institutionId, int $creatorId): User
    {
        $data['password'] = Hash::make($data['password'] ?? 'password123'); // Default password if not provided
        $data['role'] = UserRole::Teacher->value;
        $data['institution_id'] = $institutionId;
        $data['created_by'] = $creatorId;
        $data['status'] = true;
        $data['longitude'] = $data['longitude'] ?? null;
        $data['latitude'] = $data['latitude'] ?? null;
        $data['address'] = $data['address'] ?? null;
        $data['country'] = $data['country'] ?? null;
        $data['title'] = $data['title'] ?? null;
        $data['position'] = $data['position'] ?? null;
        $data['staff_number'] = $data['staff_number'] ?? null;

        if (isset($data['profile_picture']) && $data['profile_picture'] instanceof \Illuminate\Http\UploadedFile) {
            $data['profile_picture'] = $data['profile_picture']->store('profile_pictures', 'public');
        }

        return User::create($data);
    }
}
