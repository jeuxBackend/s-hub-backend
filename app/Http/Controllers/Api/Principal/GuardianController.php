<?php

namespace App\Http\Controllers\Api\Principal;

use App\Http\Controllers\Controller;
use App\Actions\Guardian\ListGuardiansAction;
use App\Actions\Guardian\CreateGuardianAction;
use App\Actions\User\UpdateUserAction;
use App\Actions\User\GetUserAction;
use App\Http\Requests\User\ListUserRequest;
use App\Http\Resources\UserResource;
use Illuminate\Http\Request;
use Throwable;

class GuardianController extends Controller
{
    public function index(ListUserRequest $request, ListGuardiansAction $action)
    {
        try {
            $requester = auth()->user();
            $guardians = $action->handle($request, $requester);
            return $this->paginatedResponse(UserResource::collection($guardians), 'Guardians retrieved successfully');
        } catch (Throwable $e) {
            return $this->exceptionResponse($e);
        }
    }

    public function store(Request $request, CreateGuardianAction $action)
    {
        $data = $request->validate([
            'first_name' => 'required|string|max:255',
            'sur_name' => 'required|string|max:255',
            'last_name' => 'nullable|string|max:255',
            'email' => 'required|email|unique:users,email',
            'phone_number' => 'required|string|unique:users,phone_number',
            'password' => 'nullable|string|min:8',
            'profile_picture' => 'nullable|image|max:2048',
            'position' => 'nullable|string|max:255',
            'guardian_type' => 'required|string|in:mother,father,guardian',
            'guardian_name' => 'nullable|string|max:255',
            'guardian_relation' => 'nullable|string|max:255',
            'guardian_phone_number' => 'nullable|string|unique:users,phone_number',
            'alternative_guardian_phone_number' => 'nullable',
            'alternative_email' => 'nullable|email|unique:users,email',
            'address' => 'nullable|string|max:255',
            'allow_alert' => 'nullable|boolean',
            'nationality' => 'nullable|string|max:100',
            'country_of_birth' => 'nullable|string|max:100',
            'primary_language' => 'nullable|string|max:100',
        ]);

        try {
            $requester = auth()->user();

            if ($request->hasFile('profile_picture')) {
                $data['profile_picture'] = $this->handleUserFileUpload($request, 'profile_picture', 'profile_pictures');
            }

            $guardian = $action->handle($data, $requester->institution_id, $requester->id);

            return $this->successResponse(new UserResource($guardian), 'Guardian created successfully', 201);
        } catch (Throwable $e) {
            return $this->exceptionResponse($e);
        }
    }

    public function show($id, GetUserAction $action)
    {
        try {
            $guardian = $action->handle($id);
            return $this->successResponse(new UserResource($guardian), 'Guardian retrieved successfully');
        } catch (Throwable $e) {
            return $this->exceptionResponse($e);
        }
    }

    public function update(Request $request, $id, UpdateUserAction $action, GetUserAction $getAction)
    {
        $data = $request->validate([
            'first_name' => 'sometimes|string|max:255',
            'sur_name' => 'sometimes|string|max:255',
            'last_name' => 'nullable|string|max:255',
            'email' => 'sometimes|email|unique:users,email,' . $id,
            'phone_number' => 'sometimes|string|unique:users,phone_number,' . $id,
            'password' => 'sometimes|string|min:8',
            'profile_picture' => 'nullable|image|max:2048',
            'position' => 'nullable|string|max:255',
            'guardian_type' => 'sometimes|string|in:mother,father,guardian',
            'guardian_name' => 'sometimes|string|max:255',
            'guardian_relation' => 'sometimes|string|max:255',
            'guardian_phone_number' => 'sometimes|string|unique:users,phone_number,' . $id,
            'alternative_guardian_phone_number' => 'sometimes',
            'alternative_email' => 'sometimes|email|unique:users,email,' . $id,
            'address' => 'sometimes|string|max:255',
            'allow_alert' => 'sometimes|boolean',
            'nationality' => 'nullable|string|max:100',
            'country_of_birth' => 'nullable|string|max:100',
            'primary_language' => 'nullable|string|max:100',
        ]);

        try {
            if ($request->hasFile('profile_picture')) {
                $data['profile_picture'] = $this->handleUserFileUpload($request, 'profile_picture', 'profile_pictures');
            }

            $guardian = $getAction->handle($id);
            $updatedGuardian = $action->handle($guardian->id, $data, auth()->user());

            return $this->successResponse(new UserResource($updatedGuardian), 'Guardian updated successfully');
        } catch (Throwable $e) {
            return $this->exceptionResponse($e);
        }
    }

    public function destroy($id, GetUserAction $getAction)
    {
        try {
            $guardian = $getAction->handle($id);
            $guardian->delete();

            return $this->successResponse(null, 'Guardian deleted successfully');
        } catch (Throwable $e) {
            return $this->exceptionResponse($e);
        }
    }
}
