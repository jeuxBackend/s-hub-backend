<?php

namespace App\Http\Controllers\Api\User;

use App\Http\Controllers\Controller;
use App\Http\Resources\UserResource;
use App\Actions\User\UpdateUserAction;
use App\Actions\User\GetUserAction;
use App\Actions\User\ListUsersAction;
use App\Actions\User\DeleteAccountAction;
use App\Actions\User\ToggleAllowAlertAction;
use App\Actions\User\ToggleNotificationAction;
use App\Http\Requests\User\UpdateUserRequest;
use App\Http\Requests\User\FilterUserRequest;
use App\Models\User;
use Illuminate\Http\Resources\Json\ResourceCollection;
use Throwable;
use App\Http\Requests\User\ChangePasswordRequest;
use App\Actions\User\ChangePasswordAction;
use App\Actions\User\UpdateContactAction;
use App\Http\Requests\User\UpdateContactRequest;
use App\Models\SchoolAlert;


class UserController extends Controller
{
    public function index(FilterUserRequest $request, ListUsersAction $listUsers)
    {
        try {
            $users = $listUsers->handle($request->validated(), auth()->user());

            $resource = new ResourceCollection(UserResource::collection($users));
            $resource->resource = $users;

            return $this->paginatedResponse($resource, 'Users fetched successfully');
        } catch (Throwable $e) {
            return $this->exceptionResponse($e);
        }
    }

    public function show(User $user, GetUserAction $getUser)
    {
        try {
            // $this->authorize('view', $user);
            $result = $getUser->handle($user->id);

            return $this->successResponse(
                [
                    'user' => new UserResource($result)
                ],
                'User fetched successfully'
            );
        } catch (Throwable $e) {
            return $this->exceptionResponse($e);
        }
    }

    public function update(UpdateUserRequest $request, User $user, UpdateUserAction $updateUser)
    {
        try {

            $data = $request->validated();
            $updated = $updateUser->handle($user->id, $data, auth()->user());
            return $this->successResponse(
                [
                    'user' => new UserResource($updated),
                ],
                'User updated successfully'
            );
        } catch (Throwable $e) {
            return $this->exceptionResponse($e);
        }
    }

    public function getAuthenticatedUser()
    {
        try {
            $user = auth()->user();
            
            if (!$user) {
                return $this->errorResponse('User not authenticated', 401);
            }
            
            // Load relationships based on user role similar to login action
            if (in_array($user->role, [\App\Enums\UserRole::Principal, \App\Enums\UserRole::Teacher, \App\Enums\UserRole::SchoolAdmin, \App\Enums\UserRole::Parent], true)) {
                $user->load(['institution']);
            }

            if ($user->role === \App\Enums\UserRole::Parent) {
                $user->load(['guardianStudents', 'familyMembers']);
            }

            if (in_array($user->role, [\App\Enums\UserRole::Principal, \App\Enums\UserRole::SchoolAdmin, \App\Enums\UserRole::Teacher, \App\Enums\UserRole::Parent], true) && $user->relationLoaded('institution') && $user->institution) {
                $activeAlertsQuery = SchoolAlert::where('institution_id', $user->institution_id);

                if ($user->role === \App\Enums\UserRole::Principal) {
                    $activeAlertsQuery->where('status', '!=', 'resolved');
                } else {
                    $activeAlertsQuery
                        ->where('status', 'active')
                        ->withinActiveCountWindow();
                }

                $user->institution->setAttribute(
                    'active_alerts_count',
                    $activeAlertsQuery->count()
                );

                if (in_array($user->role, [\App\Enums\UserRole::Teacher, \App\Enums\UserRole::SchoolAdmin], true)) {
                    $user->institution->setAttribute(
                        'potential_abduction_alerts_count',
                        SchoolAlert::where('institution_id', $user->institution_id)
                            ->where('type', 'abduction')
                            ->where('status', 'potential')
                            ->count()
                    );
                }
            }
            
            // Get unread notification count
            $unreadNotificationCount = \App\Models\NotificationLog::where('user_id', $user->id)
                ->where('is_read', false)
                ->count();

            return $this->successResponse([
                'user' => new UserResource($user),
                'role' => $user->role->value,
                'is_registered' => !is_null($user->password),
                'unread_notification_count' => $unreadNotificationCount,
            ], 'Authenticated user data retrieved successfully');
        } catch (Throwable $e) {
            return $this->exceptionResponse($e);
        }
    }

    public function updateProfile(UpdateUserRequest $request, UpdateUserAction $updateUser)
    {
        try {
            $data = $request->validated();

            if ($request->hasFile('profile_picture')) {
                $data['profile_picture'] = $this->handleUserFileUpload($request, 'profile_picture', 'profile_pictures');
            }

            $updated = $updateUser->handle(auth()->user()->id, $data, auth()->user());
            return $this->successResponse(
                [
                    'user' => new UserResource($updated),
                ],
                'Profile updated successfully'
            );
        } catch (Throwable $e) {
            return $this->exceptionResponse($e);
        }
    }

    public function destroy(User $user, DeleteAccountAction $deleteAccount)
    {
        try {

            $this->authorize('delete', $user);
            $deleteAccount->handle($user, auth()->user());

            return $this->successResponse(null, 'User deleted successfully');
        } catch (Throwable $e) {
            return $this->exceptionResponse($e);
        }
    }

    public function toggleNotification(User $user, ToggleNotificationAction $toggleNotification)
    {
        try {
            $updated = $toggleNotification->handle($user);

            return $this->successResponse(
                ['user' => new UserResource($updated)],
                'Notification status updated'
            );
        } catch (Throwable $e) {
            return $this->exceptionResponse($e);
        }
    }

    public function toggleAllowAlert(ToggleAllowAlertAction $toggleAllowAlert)
    {
        try {
            $updated = $toggleAllowAlert->handle(auth()->user());

            return $this->successResponse(
                ['user' => new UserResource($updated)],
                'Allow alert status updated'
            );
        } catch (Throwable $e) {
            return $this->exceptionResponse($e);
        }
    }

    public function toggleRemote()
    {
        try {
            $user = auth()->user();
            $user->update([
                'remote_teaching' => !$user->remote_teaching,
            ]);
            return $this->successResponse(
                ['user' => new UserResource($user)],
                'Remote teaching status updated'
            );
        } catch (Throwable $e) {
            return $this->exceptionResponse($e);
        }
    }

    public function updateContact(UpdateContactRequest $request, UpdateContactAction $action)
    {
        try {
            $user = auth()->user();

            $updated = $action->handle($request->validated(), $user);

            return $this->successResponse(
                ['user' => new UserResource($updated)],
                'Contact information updated. OTP verification may be required.'
            );
        } catch (Throwable $e) {
            return $this->exceptionResponse($e);
        }
    }

    public function changePassword(ChangePasswordRequest $request, ChangePasswordAction $action)
    {
        try {
            $user = auth()->user();

            $action->handle($request->validated(), $user);

            return $this->successResponse(null, 'Password updated successfully.');
        } catch (Throwable $e) {
            return $this->exceptionResponse($e);
        }
    }

}
