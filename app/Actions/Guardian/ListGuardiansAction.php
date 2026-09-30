<?php

namespace App\Actions\Guardian;

use App\Enums\AdminRole;
use App\Models\Admin;
use App\Models\User;
use Illuminate\Http\Request;

class ListGuardiansAction
{
    public function handle(Request $request)
    {
        $requester = auth()->user();

        $registrationStatus = $request->input('registration_status');

        $query = User::query()->where('role', 'parent');

        if ($requester instanceof Admin && $requester->role === AdminRole::Manager) {
            // A manager has no institution_id of its own — scope to every
            // institution it owns instead.
            $query->whereIn('institution_id', $requester->institutions()->pluck('id'));
        } else {
            $query
                ->where('institution_id', $requester->institution_id)
                ->when(
                    in_array($requester->role?->value ?? null, ['principal', 'school-admin'], true),
                    function ($q) use ($registrationStatus) {
                        if ($registrationStatus === 'unregistered') {
                            $q->whereNull('password');
                        } elseif ($registrationStatus !== 'all') {
                            // Default (and explicit 'registered'): unchanged
                            // from before this filter existed.
                            $q->whereNotNull('password');
                        }
                    }
                );
        }

        return $query
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
