<?php

namespace App\Actions\Teacher;

use Illuminate\Http\Request;
use App\Models\User;

class ListTeachersAction
{
    public function handle(Request $request)
    {
        $requester = auth()->user();

        // Only allow principal to access this
        // if ($requester->role !== 'principal') {
        //     return User::query()->whereRaw('1 = 0')->paginate($request->get('per_page', 10));
        // }

        $query = User::query()
            ->whereIn('role', ['teacher', 'school-admin'])
            ->where('institution_id', $requester->institution_id);

        return $query
            ->when($request->filled('name'), function ($q) use ($request) {
                $q->where(function ($sub) use ($request) {
                    $sub->where('first_name', 'like', '%' . $request->name . '%')
                        ->orWhere('sur_name', 'like', '%' . $request->name . '%');
                });
            })
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
