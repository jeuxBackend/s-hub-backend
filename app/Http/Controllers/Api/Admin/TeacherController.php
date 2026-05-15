<?php

namespace App\Http\Controllers\Api\Admin;

use App\Actions\Admin\GetGlobalTeachersAction;
use App\Actions\Admin\CreateGlobalTeacherAction;
use App\Actions\Admin\UpdateGlobalTeacherAction;
use App\Actions\Admin\DeleteGlobalTeacherAction;
use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;

class TeacherController extends Controller
{
    public function __construct(
        protected GetGlobalTeachersAction $getGlobalTeachersAction,
        protected CreateGlobalTeacherAction $createGlobalTeacherAction,
        protected UpdateGlobalTeacherAction $updateGlobalTeacherAction,
        protected DeleteGlobalTeacherAction $deleteGlobalTeacherAction
    ) {
    }

    public function index(Request $request)
    {
        $teachers = $this->getGlobalTeachersAction->handle($request->all());
        return $this->successResponse($teachers, 'Global teachers list retrieved successfully');
    }

    
    public function destroy($id)
    {
        $this->deleteGlobalTeacherAction->handle($id);
        return $this->successResponse(null, 'Teacher deleted successfully');
    }
}

