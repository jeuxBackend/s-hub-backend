<?php

namespace App\Actions\Guardian;

use App\Models\User;
use App\Enums\UserRole;
use Illuminate\Support\Facades\Hash;

class CreateGuardianAction
{
    public function handle(array $data, int $institutionId, int $creatorId): User
    {
        $data['password'] = Hash::make($data['password'] ?? 'password123');
        $data['role'] = UserRole::Parent->value;
        $data['institution_id'] = $institutionId;
        $data['created_by'] = $creatorId;
        $data['status'] = true;
        $data['longitude'] = $data['longitude'] ?? null;
        $data['latitude'] = $data['latitude'] ?? null;
        $data['address'] = $data['address'] ?? null;

        if (isset($data['profile_picture']) && $data['profile_picture'] instanceof \Illuminate\Http\UploadedFile) {
            $data['profile_picture'] = $data['profile_picture']->store('profile_pictures', 'public');
        }

        return User::create($data);
    }
}
