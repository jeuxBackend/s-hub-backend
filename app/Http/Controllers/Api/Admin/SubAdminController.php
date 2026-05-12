<?php

namespace App\Http\Controllers\Api\Admin;

use App\Actions\Admin\GetSubAdminAction;
use App\Actions\Admin\CreateSubAdminAction;
use App\Actions\Admin\UpdateSubAdminAction;
use App\Actions\Admin\DeleteSubAdminAction;
use App\Http\Controllers\Controller;
use App\Models\Admin;
use App\Enums\AdminRole;
use Illuminate\Http\Request;

class SubAdminController extends Controller
{
    public function __construct(
        protected GetSubAdminAction $getSubAdminAction,
        protected CreateSubAdminAction $createSubAdminAction,
        protected UpdateSubAdminAction $updateSubAdminAction,
        protected DeleteSubAdminAction $deleteSubAdminAction
    ) {}

    public function index(Request $request)
    {
        $subAdmins = $this->getSubAdminAction->handle($request->all());
        return $this->successResponse($subAdmins, 'Admin sub-admins list retrieved successfully');
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'first_name' => 'required|string|max:255',
            'sure_name' => 'required|string|max:255',
            'email' => 'required|email|unique:admins,email',
            'phone_number' => 'required|string|unique:admins,phone_number',
            'password' => 'required|string|min:8',
            'region' => 'nullable|array',
            'region.*' => 'string|max:255',
            'profile_image' => 'nullable|image|max:2048',
            'permissions' => 'nullable|array',
            'permissions.*' => 'string',
            'school_ids' => 'nullable|array',
            'school_ids.*' => 'exists:institutions,id',
        ]);

        $subAdmin = $this->createSubAdminAction->handle($data);
        return $this->successResponse($subAdmin, 'Sub admin created successfully', 201);
    }

    public function show($id)
    {
        $subAdmin = Admin::where('role', AdminRole::SubAdmin)->findOrFail($id);
        return $this->successResponse($subAdmin, 'Sub admin retrieved successfully');
    }

    public function update(Request $request, $id)
    {
        $data = $request->validate([
            'first_name' => 'sometimes|string|max:255',
            'sure_name' => 'sometimes|string|max:255',
            'email' => 'sometimes|email|unique:admins,email,' . $id,
            'phone_number' => 'sometimes|string|unique:admins,phone_number,' . $id,
            'password' => 'nullable|string|min:8',
            'region' => 'nullable|array',
            'region.*' => 'string|max:255',
            'profile_image' => 'nullable|image|max:2048',
            'permissions' => 'nullable|array',
            'permissions.*' => 'string',
            'school_ids' => 'nullable|array',
            'school_ids.*' => 'exists:institutions,id',
        ]);

        $subAdmin = $this->updateSubAdminAction->handle($data, $id);
        return $this->successResponse($subAdmin, 'Sub admin updated successfully');
    }

    public function destroy($id)
    {
        $this->deleteSubAdminAction->handle($id);
        return $this->successResponse(null, 'Sub admin deleted successfully');
    }
}
