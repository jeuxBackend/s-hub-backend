<?php

namespace App\Actions\Manager;

use App\Models\User;
use App\Enums\UserRole;
use Illuminate\Support\Facades\Hash;

class CreatePrincipalAction
{
    public function handle(array $data, int $institutionId)
    {
        $data['password'] = Hash::make($data['password']);
        $data['role'] = UserRole::Principal->value;
        $data['institution_id'] = $institutionId;


        $data['longitude'] = $data['longitude'] ?? null;
        $data['latitude'] = $data['latitude'] ?? null;
        $data['address'] = $data['address'] ?? null;

        return User::create($data);
    }
}
