<?php

namespace App\Http\Controllers\Api\Manager;

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
        protected CreateSchoolAction $createSchoolAction,
        protected UpdateSchoolAction $updateSchoolAction,
        protected DeleteSchoolAction $deleteSchoolAction
    ) {}

    public function index(Request $request)
    {
        $data = $request->all();
        $data['manager_id'] = auth()->id();
        
        $schools = $this->getSchoolsAction->handle($data);
        return $this->successResponse($schools, 'Schools retrieved successfully');
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'category_id' => 'required|exists:categories,id',
            'name' => 'required|string|max:255',
            'email' => 'required|email|unique:institutions,email',
            'phone_number' => 'required|string|unique:institutions,phone_number',
            'physical_address' => 'required|string',
        ]);

        $data['manager_id'] = auth()->id();
        $data['status'] = 'pending';

        $school = $this->createSchoolAction->handle($data);
        return $this->successResponse($school, 'School created successfully', 201);
    }

    public function show($id)
    {
        $school = Institution::with(['manager:id,first_name,sure_name,email', 'category'])
            ->where('manager_id', auth()->id())
            ->findOrFail($id);
        return $this->successResponse($school, 'School retrieved successfully');
    }

    public function update(Request $request, $id)
    {
        $school = Institution::where('manager_id', auth()->id())->findOrFail($id);

        $data = $request->validate([
            'category_id' => 'sometimes|exists:categories,id',
            'name' => 'sometimes|string|max:255',
            'email' => 'sometimes|email|unique:institutions,email,' . $id,
            'phone_number' => 'sometimes|string|unique:institutions,phone_number,' . $id,
            'physical_address' => 'sometimes|string',
        ]);

        if (isset($data['status'])) {
            unset($data['status']);
        }

        $school = $this->updateSchoolAction->handle($data, $id);
        return $this->successResponse($school, 'School updated successfully');
    }

    public function destroy($id)
    {
        $school = Institution::where('manager_id', auth()->id())->findOrFail($id);
        $this->deleteSchoolAction->handle($id);
        return $this->successResponse(null, 'School deleted successfully');
    }
}
