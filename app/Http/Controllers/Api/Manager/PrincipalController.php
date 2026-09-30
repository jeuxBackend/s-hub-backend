<?php

namespace App\Http\Controllers\Api\Manager;

use App\Actions\Manager\CreatePrincipalAction;
use App\Actions\Manager\UpdatePrincipalAction;
use App\Actions\User\ToggleUserStatusAction;
use App\Http\Controllers\Controller;
use App\Models\Institution;
use App\Models\User;
use App\Enums\UserRole;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PrincipalController extends Controller
{
    public function __construct(
        protected CreatePrincipalAction $createPrincipalAction,
        protected UpdatePrincipalAction $updatePrincipalAction
    ) {
    }

    public function index(Request $request)
    {
        $managerId = auth()->id();
        $principals = User::where('role', UserRole::Principal)
            ->whereHas('institution', function ($query) use ($managerId) {
                $query->where('manager_id', $managerId);
            })
            ->when($request->filled('name'), function ($query) use ($request) {
                $search = $request->input('name');
                $query->where(function ($q) use ($search) {
                    $q->where('first_name', 'like', "%{$search}%")
                        ->orWhere('last_name', 'like', "%{$search}%")
                        ->orWhere('sur_name', 'like', "%{$search}%");
                });
            })
            ->when($request->filled('email'), fn ($query) => $query->where('email', 'like', '%' . $request->input('email') . '%'))
            ->when($request->filled('institution_id'), fn ($query) => $query->where('institution_id', $request->input('institution_id')))
            ->when($request->has('status'), fn ($query) => $query->where('status', $request->boolean('status')))
            ->with('institution')
            ->orderBy('id', 'desc')
            ->paginate($request->input('per_page', 20));

        return $this->paginatedResponse(
            JsonResource::collection($principals),
            'Principals retrieved successfully'
        );
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'first_name' => 'required|string|max:255',
            'sur_name' => 'required|string|max:255',
            'last_name' => 'nullable|string|max:255',
            'email' => 'required|email|unique:users,email',
            'phone_number' => 'required|string|unique:users,phone_number',
            'password' => 'required|string|min:8',
            'institution_id' => 'required|exists:institutions,id',
            'profile_picture' => 'nullable|image|mimes:jpeg,png,jpg,gif|max:2048',
        ]);

        if ($request->hasFile('profile_picture')) {
            $data['profile_picture'] = $this->handleUserFileUpload($request, 'profile_picture', 'profile_pictures');
        }

        $school = Institution::where('manager_id', auth()->id())->findOrFail($data['institution_id']);

        if ($school->status !== 'approved') {
            return $this->errorResponse('Cannot add a principal to a pending or rejected school.', 403);
        }

        if (User::where('role', UserRole::Principal)->where('institution_id', $school->id)->exists()) {
            return $this->errorResponse('This school already has a principal assigned.', 422);
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
            'last_name' => 'nullable|string|max:255',
            'email' => 'sometimes|email|unique:users,email,' . $id,
            'phone_number' => 'sometimes|string|unique:users,phone_number,' . $id,
            'password' => 'sometimes|string|min:8',
            'institution_id' => 'sometimes|exists:institutions,id',
            'profile_picture' => 'sometimes|nullable|image|mimes:jpeg,png,jpg,gif|max:2048',
        ]);

        if ($request->hasFile('profile_picture')) {
            $data['profile_picture'] = $this->handleUserFileUpload($request, 'profile_picture', 'profile_pictures');
        }

        if (isset($data['institution_id'])) {
            $school = Institution::where('manager_id', $managerId)->findOrFail($data['institution_id']);
            if ($school->status !== 'approved') {
                return $this->errorResponse('Cannot assign a principal to a pending or rejected school.', 403);
            }

            if ((int) $data['institution_id'] !== (int) $principal->institution_id
                && User::where('role', UserRole::Principal)->where('institution_id', $school->id)->exists()) {
                return $this->errorResponse('This school already has a principal assigned.', 422);
            }
        }

        $updatedPrincipal = $this->updatePrincipalAction->handle($principal, $data);
        $updatedPrincipal->load('institution');

        return $this->successResponse($updatedPrincipal, 'Principal updated successfully');
    }

    public function toggleBlock($id, ToggleUserStatusAction $action)
    {
        $managerId = auth()->id();
        User::where('role', UserRole::Principal)
            ->where('id', $id)
            ->whereHas('institution', function ($query) use ($managerId) {
                $query->where('manager_id', $managerId);
            })
            ->firstOrFail();

        $principal = $action->handle($id);
        $principal->load('institution');

        return $this->successResponse($principal, 'Principal status toggled successfully');
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
