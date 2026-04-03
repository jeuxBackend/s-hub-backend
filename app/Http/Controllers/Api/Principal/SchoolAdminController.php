<?php

namespace App\Http\Controllers\Api\Principal;

use App\Http\Controllers\Controller;
use App\Actions\SchoolAdmin\ListSchoolAdminsAction;
use App\Http\Requests\User\ListUserRequest;
use App\Http\Resources\UserResource;
use Illuminate\Http\Resources\Json\ResourceCollection;
use Throwable;

class SchoolAdminController extends Controller
{
    public function __invoke(ListUserRequest $request, ListSchoolAdminsAction $action)
    {
        try {
            $requester = auth()->user();

            $schoolAdmins = $action->handle($request, $requester);

            $resource = new ResourceCollection(UserResource::collection($schoolAdmins));
            $resource->resource = $schoolAdmins;

            return $this->paginatedResponse($resource);
        } catch (Throwable $e) {
            return $this->exceptionResponse($e);
        }
    }
}
