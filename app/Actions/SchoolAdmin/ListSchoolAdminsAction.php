<?php

namespace App\Actions\SchoolAdmin;

use Illuminate\Http\Request;
use App\Models\User;
use App\Enums\UserRole;

class ListSchoolAdminsAction
{
    public function handle(Request $request, $requester)
    {
        $query = User::query()->where('role', UserRole::SchoolAdmin->value);

        if ($requester instanceof \App\Models\User && $requester->isRole(UserRole::Principal)) {
            // Principal can see school admins in their school
            $query->where('institution_id', $requester->institution_id);
        } elseif ($requester instanceof \App\Models\Admin && $requester->role->value === 'manager') {
            // Manager can see school admins in schools they manage
            $query->whereHas('institution', function ($q) use ($requester) {
                $q->where('manager_id', $requester->id);
            });
        } else {
            // Block all other roles
            $query->whereRaw('1 = 0');
        }

        return $query
            ->when(
                $request->filled('name'),
                fn($q) =>
                $q->where(function ($sub) use ($request) {
                    $sub->where('first_name', 'like', '%' . $request->name . '%')
                        ->orWhere('sur_name', 'like', '%' . $request->name . '%');
                })
            )
            ->when(
                $request->filled('phone'),
                fn($q) =>
                $q->where('phone_number', 'like', '%' . $request->phone . '%')
            )
            ->when(
                $request->filled('email'),
                fn($q) =>
                $q->where('email', 'like', '%' . $request->email . '%')
            )
            ->latest()
            ->paginate($request->get('per_page', 10));
    }
}
