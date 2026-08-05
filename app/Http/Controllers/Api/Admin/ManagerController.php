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
        $managers = $this->getManagerAction->handle($request->all());
        return $this->successResponse($managers, 'Admin managers list');
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'first_name' => 'required|string|max:255',
            'sure_name' => 'required|string|max:255',
            'last_name' => 'nullable|string|max:255',
            'email' => 'required|email|unique:admins,email',
            'phone_number' => 'required|string|unique:admins,phone_number',
            'password' => 'required|string|min:8',
        ]);

        $manager = $this->createManagerAction->handle($data);
        return $this->successResponse($manager, 'Manager created successfully', 201);
    }

    public function show($id)
    {
        $manager = Admin::where('role', \App\Enums\AdminRole::Manager)->findOrFail($id);
        return $this->successResponse($manager, 'Manager retrieved successfully');
    }

    public function update(Request $request, $id)
    {
        $data = $request->validate([
            'first_name' => 'sometimes|string|max:255',
            'sure_name' => 'sometimes|string|max:255',
            'last_name' => 'nullable|string|max:255',
            'email' => 'sometimes|email|unique:admins,email,' . $id,
            'phone_number' => 'sometimes|string|unique:admins,phone_number,' . $id,
            'password' => 'nullable|string|min:8',
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
        $schools = $this->getManagerSchoolsAction->handle($id);
        return $this->successResponse($schools, 'Manager schools list');
    }
}
