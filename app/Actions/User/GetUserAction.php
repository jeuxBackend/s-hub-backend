<?php

namespace App\Actions\User;

use App\Models\User;
use Illuminate\Support\Facades\Gate;
class GetUserAction
{
    public function handle(int $id): User
    {
         $user = User::findOrFail($id);

        // Gate::authorize('view', $user);
       $user->load('institution');
        return $user;
    }
}
