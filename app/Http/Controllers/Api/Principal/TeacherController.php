<?php

namespace App\Http\Controllers\Api\Principal;

use App\Http\Controllers\Controller;
use App\Actions\Teacher\ListTeachersAction;
use App\Http\Requests\User\ListUserRequest;
use App\Http\Resources\UserResource;
use Throwable;

class TeacherController extends Controller
{
    public function __invoke(ListTeachersRequest $request, ListTeachersAction $action)
    {
        $this->authorize('viewAny', Teacher::class);   // or User with teacher scope

        $teachers = $action->handle($request);

        return $this->paginatedResponse($teachers);
    }
}
