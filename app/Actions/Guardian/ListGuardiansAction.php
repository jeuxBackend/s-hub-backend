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
            ->where('created_by', $requester->id)
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
