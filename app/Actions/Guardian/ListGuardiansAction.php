<?php

namespace App\Actions\Guardian;

use App\Models\User;
use Illuminate\Http\Request;

class ListGuardiansAction
{
    public function handle(Request $request)
    {
        $requester = auth()->user();

        return User::query()
            ->where('role', 'parent')
            ->where('institution_id', $requester->institution_id)
            ->when($request->filled('name'), function ($q) use ($request) {
                $q->where(function ($sub) use ($request) {
                    $sub->where('first_name', 'like', '%' . $request->name . '%')
                        ->orWhere('sur_name', 'like', '%' . $request->name . '%');
                });
            })
            ->when($request->filled('phone'), fn ($q) =>
                $q->where('phone_number', 'like', '%' . $request->phone . '%')
            )
            ->when($request->filled('email'), fn ($q) =>
                $q->where('email', 'like', '%' . $request->email . '%')
            )
            ->latest()
            ->paginate($request->get('per_page', 10));
    }
}
