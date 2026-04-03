<?php

namespace App\Actions\SchoolAdmin;

use Illuminate\Http\Request;
use App\Models\User;

class ListSchoolAdminsAction
{
    public function handle(Request $request)
    {
        $requester = auth()->user();

        $query = User::query()->where('created_by', $requester->id)->where('role', 'school_admin');

        // if ($requester->role === 'principal') {
        //     // Only school_admins created by this principal
        //     $query->where('created_by', $requester->id);
        // // } else {
        // //     // Block all other roles
        // //     $query->whereRaw('1 = 0');
        // }

        return $query
            ->when($request->filled('name'), fn ($q) =>
                $q->where('name', 'like', '%' . $request->name . '%')
            )
            ->when($request->filled('phone'), fn ($q) =>
                $q->where('phone', 'like', '%' . $request->phone . '%')
            )
            ->when($request->filled('email'), fn ($q) =>
                $q->where('email', 'like', '%' . $request->email . '%')
            )
            ->latest()
            ->paginate($request->get('per_page', 10));
    }
}
