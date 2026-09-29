<?php

namespace App\Actions\Guardian;

use App\Models\User;
use Illuminate\Http\Request;

class ListGuardiansAction
{
    public function handle(Request $request)
    {
        $requester = auth()->user();

        $registrationStatus = $request->input('registration_status');

        return User::query()
            ->where('role', 'parent')
            ->where('institution_id', $requester->institution_id)
            ->when(
                in_array($requester->role?->value ?? null, ['principal', 'school-admin'], true),
                function ($query) use ($registrationStatus) {
                    if ($registrationStatus === 'unregistered') {
                        $query->whereNull('password');
                    } elseif ($registrationStatus !== 'all') {
                        // Default (and explicit 'registered'): unchanged
                        // from before this filter existed.
                        $query->whereNotNull('password');
                    }
                }
            )
            ->with([
                'guardianStudents.classroom',
                'guardianStudents.studentInvoices',
            ])
            ->when($request->filled('guardian_name'), function ($q) use ($request) {
                $q->where(function ($sub) use ($request) {
                    $sub->where('first_name', 'like', '%' . $request->guardian_name . '%')
                        ->orWhere('last_name', 'like', '%' . $request->guardian_name . '%')
                        ->orWhere('sur_name', 'like', '%' . $request->guardian_name . '%');
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
