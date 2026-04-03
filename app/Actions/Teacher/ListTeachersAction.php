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
            ->where('role', 'teacher')
            ->where('created_by', $requester->id);

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
