<?php

namespace App\Http\Controllers\Api\Manager;

use App\Actions\Manager\CreatePrincipalAction;
use App\Actions\Manager\UpdatePrincipalAction;
use App\Http\Controllers\Controller;
use App\Models\Institution;
use App\Models\User;
use App\Enums\UserRole;
use Illuminate\Http\Request;

class PrincipalController extends Controller
{
    public function __construct(
        protected CreatePrincipalAction $createPrincipalAction,
        protected UpdatePrincipalAction $updatePrincipalAction
    ) {
    }

    public function index()
    {
        $managerId = auth()->id();
        $principals = User::where('role', UserRole::Principal)
            ->whereHas('institution', function ($query) use ($managerId) {
                $query->where('manager_id', $managerId);
            })
            ->with('institution')
            ->get();

        return $this->successResponse($principals, 'Principals retrieved successfully');
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'first_name' => 'required|string|max:255',
            'sur_name' => 'required|string|max:255',
            'email' => 'required|email|unique:users,email',
            'phone_number' => 'required|string|unique:users,phone_number',
            'password' => 'required|string|min:8',
            'institution_id' => 'required|exists:institutions,id',
        ]);

        $school = Institution::where('manager_id', auth()->id())->findOrFail($data['institution_id']);

        if ($school->status !== 'approved') {
            return response()->json([
                'status' => 'error',
                'message' => 'Cannot add a principal to a pending or rejected school.'
            ], 403);
        }

        $principal = $this->createPrincipalAction->handle($data, $data['institution_id']);

        return $this->successResponse($principal, 'Principal created and assigned successfully', 201);
    }

    public function show($id)
    {
        $managerId = auth()->id();
        $principal = User::where('role', UserRole::Principal)
            ->where('id', $id)
            ->whereHas('institution', function ($query) use ($managerId) {
                $query->where('manager_id', $managerId);
            })
            ->with('institution')
            ->firstOrFail();

        return $this->successResponse($principal, 'Principal details retrieved successfully');
    }

    public function update(Request $request, $id)
    {
        $managerId = auth()->id();
        $principal = User::where('role', UserRole::Principal)
            ->where('id', $id)
            ->whereHas('institution', function ($query) use ($managerId) {
                $query->where('manager_id', $managerId);
            })
            ->firstOrFail();

        $data = $request->validate([
            'first_name' => 'sometimes|string|max:255',
            'sur_name' => 'sometimes|string|max:255',
            'email' => 'sometimes|email|unique:users,email,' . $id,
            'phone_number' => 'sometimes|string|unique:users,phone_number,' . $id,
            'password' => 'sometimes|string|min:8',
            'institution_id' => 'sometimes|exists:institutions,id',
        ]);

        if (isset($data['institution_id'])) {
            $school = Institution::where('manager_id', $managerId)->findOrFail($data['institution_id']);
            if ($school->status !== 'approved') {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Cannot assign a principal to a pending or rejected school.'
                ], 403);
            }
        }

        $updatedPrincipal = $this->updatePrincipalAction->handle($principal, $data);

        return $this->successResponse($updatedPrincipal, 'Principal updated successfully');
    }

    public function destroy($id)
    {
        $managerId = auth()->id();
        $principal = User::where('role', UserRole::Principal)
            ->where('id', $id)
            ->whereHas('institution', function ($query) use ($managerId) {
                $query->where('manager_id', $managerId);
            })
            ->firstOrFail();

        $principal->delete();

        return $this->successResponse(null, 'Principal deleted successfully');
    }
}
