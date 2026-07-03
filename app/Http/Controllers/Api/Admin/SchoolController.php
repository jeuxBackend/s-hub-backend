<?php

namespace App\Http\Controllers\Api\Admin;

use App\Actions\Institution\CreateSchoolAction;
use App\Actions\Institution\DeleteSchoolAction;
use App\Actions\Institution\GetSchoolsAction;
use App\Actions\Institution\UpdateSchoolAction;
use App\Http\Controllers\Controller;
use App\Models\Institution;
use Illuminate\Http\Request;

class SchoolController extends Controller
{
    public function __construct(
        protected GetSchoolsAction $getSchoolsAction,
        // protected CreateSchoolAction $createSchoolAction,
        protected UpdateSchoolAction $updateSchoolAction,
        protected DeleteSchoolAction $deleteSchoolAction
    ) {
    }

    public function index(Request $request)
    {
        $schools = $this->getSchoolsAction->handle($request->all());
        return $this->successResponse($schools, 'Schools retrieved successfully');
    }

    // public function store(Request $request)
    // {
    //     $data = $request->validate([
    //         'name' => 'required|string|max:255',
    //         'manager_id' => 'required|exists:admins,id',
    //         'category_id' => 'nullable|exists:categories,id',
    //         'email' => 'required|email|unique:institutions,email',
    //         'phone_number' => 'required|string|unique:institutions,phone_number',
    //         'physical_address' => 'nullable|string',
    //     ]);

    //     $school = $this->createSchoolAction->handle($data);
    //     return $this->successResponse($school, 'School created successfully', 201);
    // }

    public function show(string $id)
    {
        $school = Institution::with(['manager', 'category', 'principal'])->findOrFail($id);
        return $this->successResponse($school, 'School retrieved successfully');
    }

    public function update(Request $request, string $id)
    {
        $data = $request->validate([
            'name' => 'sometimes|string|max:255',
            'manager_id' => 'sometimes|exists:admins,id',
            'category_id' => 'nullable|exists:categories,id',
            'email' => 'sometimes|email|unique:institutions,email,' . $id,
            'phone_number' => 'sometimes|string|unique:institutions,phone_number,' . $id,
            'physical_address' => 'nullable|string',
            'alert_feature_enabled' => 'sometimes|boolean',
            'allowed_alert_types' => 'nullable|array',
            'allowed_alert_types.*' => 'in:abduction,emergency',
        ]);

        $school = $this->updateSchoolAction->handle($data, $id);
        return $this->successResponse($school, 'School updated successfully');
    }

    public function toggleAlertFeature(Request $request, string $id)
    {
        $data = $request->validate([
            'alert_feature_enabled' => 'required|boolean',
            'allowed_alert_types' => 'nullable|array',
            'allowed_alert_types.*' => 'in:abduction,emergency',
        ]);

        $school = Institution::findOrFail($id);
        $school->update([
            'alert_feature_enabled' => $data['alert_feature_enabled'],
            'allowed_alert_types' => $data['allowed_alert_types'] ?? $school->allowed_alert_types ?? ['abduction', 'emergency'],
        ]);

        return $this->successResponse($school, 'School alert feature updated successfully');
    }

    public function destroy(string $id)
    {
        $this->deleteSchoolAction->handle($id);
        return $this->successResponse(null, 'School deleted successfully');
    }
}
