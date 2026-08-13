<?php

namespace App\Http\Controllers\Api\Principal;

use App\Actions\SchoolAdmin\ListSchoolAdminsAction;
use App\Actions\User\ChangeUserRoleAction;
use App\Http\Controllers\Controller;
use App\Http\Requests\User\ListUserRequest;
use App\Http\Resources\UserResource;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\ResourceCollection;
use Illuminate\Support\Facades\Log;
use Throwable;

class SchoolAdminController extends Controller
{
    public function index(ListUserRequest $request, ListSchoolAdminsAction $action)
    {
        try {
            $requester = auth()->user();

            if (
                !($requester instanceof \App\Models\Admin && $requester->role->value === 'manager') &&
                !($requester instanceof \App\Models\User && $requester->isRole(\App\Enums\UserRole::Principal))
            ) {
                abort(403, 'Unauthorized. Only Principals and Managers can view School Admins.');
            }

            $schoolAdmins = $action->handle($request, $requester);

            $resource = new ResourceCollection(UserResource::collection($schoolAdmins));
            $resource->resource = $schoolAdmins;

            return $this->paginatedResponse($resource);
        } catch (Throwable $e) {
            return $this->exceptionResponse($e);
        }
    }

    public function makeSubAdmin(Request $request, ChangeUserRoleAction $action)
    {
        try {
            $request->validate([
                'user_id' => 'required|exists:users,id',
                'role' => 'sometimes|string|in:school-admin,teacher,parent',
                'permissions' => 'required'
            ]);

            $requester = auth()->user();
            $role = $request->role ?? \App\Enums\UserRole::SchoolAdmin->value;
            $user = $action->handle($request->user_id, $role, $requester, $request->permissions);

            return $this->successResponse($user, 'User permissions updated successfully');

        } catch (Throwable $e) {
            return $this->exceptionResponse($e);
        }
    }

    public function updatePermissions(Request $request)
    {
        try {
            $request->validate([
                'user_id' => 'required|exists:users,id',
                'permissions' => 'required'
            ]);

            $requester = auth()->user();
            $user = User::findOrFail($request->user_id);

            if ($user->institution_id !== $requester->institution_id) {
                abort(403, 'You can only modify users within your own institution.');
            }

            $user->permissions = $request->permissions;
            $user->save();

            return $this->successResponse($user, 'User permissions updated successfully');
        } catch (Throwable $e) {
            return $this->exceptionResponse($e);
        }
    }

    public function getTeachersListForSchoolAdmin()
    {
        try {
            $requester = auth()->user();
            $teacherQuery = User::where('institution_id', $requester->institution_id)
                ->where('role', \App\Enums\UserRole::Teacher->value)
                ->whereNotNull('password');

            $teachers = (clone $teacherQuery)
                ->get(['id', 'first_name', 'last_name', 'sur_name', 'gender'])
                ->map(function ($user) {
                return [
                    'id' => $user->id,
                    'full_name' => $user->full_name,
                    'gender' => $user->gender?->value,
                ];
            });

            $genderCounts = (clone $teacherQuery)
                ->toBase()
                ->reorder()
                ->select('gender', \Illuminate\Support\Facades\DB::raw('COUNT(*) as total'))
                ->groupBy('gender')
                ->pluck('total', 'gender');

            return $this->successResponse([
                'teachers' => $teachers,
                'total_teachers' => $teachers->count(),
                'male_teachers' => (int) ($genderCounts[\App\Enums\GenderType::Male->value] ?? 0),
                'female_teachers' => (int) ($genderCounts[\App\Enums\GenderType::Female->value] ?? 0),
            ], 'Teachers list retrieved successfully');
        } catch (Throwable $e) {
            return $this->exceptionResponse($e);
        }
    }

    public function removeSchoolAdmin(int $id)
    {
        try {
            $requester = auth()->user();
            $user = User::findOrFail($id);

            if ($user->institution_id !== $requester->institution_id) {
                abort(403, 'You can only modify users within your own institution.');
            }

            if ($user->role->value == \App\Enums\UserRole::SchoolAdmin->value) {
                $user->role = \App\Enums\UserRole::Teacher->value;
                $user->permissions = null;
                $user->save();
            } else {
                return $this->errorResponse('Teacher not a school admin', 400);
            }

            return $this->successResponse('School admin removed successfully');
        } catch (Throwable $e) {
            Log::error($e->getMessage());
            return $this->exceptionResponse($e);
        }
    }

    public function getAvailablePermissions()
    {
        return $this->successResponse(\App\Enums\SchoolAdminPermission::options(), 'Available permissions retrieved successfully');
    }
}
