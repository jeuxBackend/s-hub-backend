<?php

namespace App\Http\Controllers\Api\Admin;

use App\Actions\Admin\GetManagerAction;
use App\Actions\Admin\CreateManagerAction;
use App\Actions\Admin\UpdateManagerAction;
use App\Actions\Admin\DeleteManagerAction;
use App\Actions\Dashboard\GetManagerSchoolsAction;
use App\Http\Controllers\Controller;
use App\Models\Admin;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class ManagerController extends Controller
{
    public function __construct(
        protected GetManagerAction $getManagerAction,
        protected CreateManagerAction $createManagerAction,
        protected UpdateManagerAction $updateManagerAction,
        protected DeleteManagerAction $deleteManagerAction,
        protected GetManagerSchoolsAction $getManagerSchoolsAction
    ) {}

    public function index(Request $request)
    {
        $data = $request->all();
        $data['institution_ids'] = auth()->user()->assignedInstitutionIds();

        $managers = $this->getManagerAction->handle($data);
        return $this->paginatedResponse(
            \Illuminate\Http\Resources\Json\JsonResource::collection($managers),
            'Admin managers list'
        );
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'first_name' => 'required|string|max:255',
            'sure_name' => 'required|string|max:255',
            'last_name' => 'nullable|string|max:255',
            'email' => ['required', 'email', Rule::unique('admins', 'email'), Rule::unique('users', 'email')],
            'phone_number' => 'required|string|unique:admins,phone_number',
            'state' => 'nullable|string|max:100',
            'province' => 'nullable|string|max:100',
            'city' => 'nullable|string|max:100',
            'password' => 'required|string|min:8',
            'emergency_contact_name' => 'nullable|string|max:255',
            'emergency_contact_phone' => 'nullable|string|max:255',
            'status' => 'nullable|in:active,inactive',
            'country' => 'nullable|string|size:2',
            'profile_image' => 'nullable|image|mimes:jpeg,png,jpg,webp|max:2048',
        ]);

        $manager = $this->createManagerAction->handle($data);
        return $this->successResponse($manager, 'Manager created successfully', 201);
    }

    public function show($id)
    {
        $manager = Admin::where('role', \App\Enums\AdminRole::Manager)->findOrFail($id);
        $this->assertInScope($manager);
        return $this->successResponse($manager, 'Manager retrieved successfully');
    }

    public function update(Request $request, $id)
    {
        // Uniqueness is only checked when the email is actually being changed, so saving a
        // manager with their current email never fails (even if a user shares that email).
        $emailRules = ['sometimes', 'email'];

        if (strcasecmp((string) $request->input('email'), (string) Admin::findOrFail($id)->email) !== 0) {
            $emailRules[] = Rule::unique('admins', 'email')->ignore($id);
            $emailRules[] = Rule::unique('users', 'email');
        }

        $data = $request->validate([
            'first_name' => 'sometimes|string|max:255',
            'sure_name' => 'sometimes|string|max:255',
            'last_name' => 'nullable|string|max:255',
            'email' => $emailRules,
            'phone_number' => 'sometimes|string|unique:admins,phone_number,' . $id,
            'state' => 'sometimes|nullable|string|max:100',
            'province' => 'sometimes|nullable|string|max:100',
            'city' => 'sometimes|nullable|string|max:100',
            'password' => 'nullable|string|min:8',
            'emergency_contact_name' => 'sometimes|nullable|string|max:255',
            'emergency_contact_phone' => 'sometimes|nullable|string|max:255',
            'status' => 'sometimes|in:active,inactive',
            'country' => 'sometimes|nullable|string|size:2',
            'profile_image' => 'nullable|image|mimes:jpeg,png,jpg,webp|max:2048',
        ]);

        $manager = $this->updateManagerAction->handle($data, $id);
        return $this->successResponse($manager, 'Manager updated successfully');
    }

    public function destroy($id)
    {
        $this->deleteManagerAction->handle($id);
        return $this->successResponse(null, 'Manager deleted successfully');
    }

    public function getManagerSchools($id)
    {
        $manager = Admin::where('role', \App\Enums\AdminRole::Manager)->findOrFail($id);
        $this->assertInScope($manager);

        $ids = auth()->user()->assignedInstitutionIds();
        $schools = $this->getManagerSchoolsAction->handle($id, $ids);
        return $this->successResponse($schools, 'Manager schools list');
    }

    /**
     * Abort with 404 if this manager owns none of the sub-admin's assigned
     * schools (no-op for admin, manager, or an unrestricted sub-admin).
     */
    private function assertInScope(Admin $manager): void
    {
        $ids = auth()->user()->assignedInstitutionIds();

        if ($ids !== null && !$manager->institutions()->whereIn('id', $ids)->exists()) {
            abort(404);
        }
    }
}
