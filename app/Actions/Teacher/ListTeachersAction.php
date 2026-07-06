<?php

namespace App\Actions\Teacher;

use Illuminate\Http\Request;
use App\Models\User;

class ListTeachersAction
{
    public function handle(Request $request)
    {
        $requester = auth()->user();

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
            ->when($request->filled('phone'), function ($q) use ($request) {
                $q->where('phone_number', 'like', '%' . $request->phone . '%');
            })
            ->when($request->filled('email'), function ($q) use ($request) {
                $q->where('email', 'like', '%' . $request->email . '%');
            })
            ->latest()
            ->paginate($request->get('per_page', 10))
            ->through(function ($user) {
                $user->is_approved = $user->password !== null;
                return $user;
            });
    }
}