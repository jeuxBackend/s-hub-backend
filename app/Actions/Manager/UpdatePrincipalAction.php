<?php

namespace App\Actions\Manager;

use App\Models\User;
use Illuminate\Support\Facades\Hash;

class UpdatePrincipalAction
{
    public function handle(User $principal, array $data)
    {
        if (isset($data['password'])) {
            $data['password'] = Hash::make($data['password']);
        }

        $principal->update($data);

        return $principal;
    }
}
