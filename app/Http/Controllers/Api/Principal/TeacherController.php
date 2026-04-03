<?php

namespace App\Http\Controllers\Api\Principal;

use App\Http\Controllers\Controller;
use App\Actions\Teacher\ListTeachersAction;
use App\Http\Requests\User\ListUserRequest;
use App\Http\Resources\UserResource;
use Illuminate\Http\Resources\Json\ResourceCollection;
use Throwable;

class TeacherController extends Controller
{
    public function __invoke(ListUserRequest $request, ListTeachersAction $action)
    {
        try {
            $requester = auth()->user();

            // ✅ Policy check: only allow allowed roles
            $this->authorize('viewAny', \App\Models\User::class);

            $result = $action->handle($request, $requester);

            return $this->paginatedResponse(
                UserResource::collection($result)
            );
        } catch (Throwable $e) {
            return $this->exceptionResponse($e);
        }
    }
}
