<?php

namespace App\Http\Controllers\Api\Principal;

use App\Http\Controllers\Controller;
use App\Actions\Guardian\ListGuardiansAction;
use App\Http\Requests\User\ListUserRequest;
use App\Http\Resources\UserResource;
use Illuminate\Http\Resources\Json\ResourceCollection;
use Throwable;

class GuardianController extends Controller
{
    public function __invoke(ListUserRequest $request, ListGuardiansAction $action)
    {
        try {
            $requester = auth()->user();

            $guardians = $action->handle($request, $requester);

            $resource = new ResourceCollection(UserResource::collection($guardians));
            $resource->resource = $guardians;

            return $this->paginatedResponse($resource);
        } catch (Throwable $e) {
            return $this->exceptionResponse($e);
        }
    }
}


