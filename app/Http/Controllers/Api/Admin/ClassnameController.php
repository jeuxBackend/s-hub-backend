<?php

namespace App\Http\Controllers\Api\Admin;

use App\Actions\Classname\DeleteClassnameAction;
use App\Actions\Classname\ListClassnamesAction;
use App\Actions\Classname\StoreClassnameAction;
use App\Actions\Classname\UpdateClassnameAction;
use App\Enums\AdminRole;
use App\Http\Controllers\Controller;
use App\Http\Requests\Classname\StoreClassnameRequest;
use App\Http\Requests\Classname\UpdateClassnameRequest;
use App\Http\Resources\ClassnameResource;
use App\Models\Classname;
use App\Models\Institution;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\Request;
use Throwable;

class ClassnameController extends Controller
{
    public function index(Request $request, ListClassnamesAction $listClassnames)
    {
        $request->validate([
            'institution_id' => ['required', 'exists:institutions,id'],
        ]);

        try {
            $classnames = $listClassnames->handle($request->only(['name', 'institution_id']));

            return $this->paginatedResponse(
                ClassnameResource::collection($classnames),
                'Class names fetched successfully'
            );
        } catch (Throwable $e) {
            return $this->exceptionResponse($e);
        }
    }

    public function show(Classname $classname)
    {
        try {
            return $this->successResponse(
                new ClassnameResource($classname),
                'Class name fetched successfully'
            );
        } catch (Throwable $e) {
            return $this->exceptionResponse($e);
        }
    }

    public function store(StoreClassnameRequest $request, StoreClassnameAction $createClassname)
    {
        try {
            $this->authorizeInstitution((int) $request->input('institution_id'));

            $classname = $createClassname->handle($request->validated());

            return $this->successResponse(
                new ClassnameResource($classname),
                'Class name created successfully',
                201
            );
        } catch (Throwable $e) {
            return $this->exceptionResponse($e);
        }
    }

    public function update(UpdateClassnameRequest $request, Classname $classname, UpdateClassnameAction $updateClassname)
    {
        try {
            $this->authorizeInstitution($classname->institution_id);

            $updated = $updateClassname->handle($classname, $request->validated());

            return $this->successResponse(
                new ClassnameResource($updated),
                'Class name updated successfully'
            );
        } catch (Throwable $e) {
            return $this->exceptionResponse($e);
        }
    }

    public function destroy(Classname $classname, DeleteClassnameAction $deleteClassname)
    {
        try {
            $this->authorizeInstitution($classname->institution_id);

            $deleteClassname->handle($classname);

            return $this->successResponse(null, 'Class name deleted successfully');
        } catch (Throwable $e) {
            return $this->exceptionResponse($e);
        }
    }

    /**
     * Admins/sub-admins manage class names for any institution. Managers are
     * restricted to institutions they own (Institution.manager_id).
     */
    private function authorizeInstitution(int $institutionId): void
    {
        $admin = auth()->user();

        if ($admin->role === AdminRole::Manager) {
            $owns = Institution::where('id', $institutionId)
                ->where('manager_id', $admin->id)
                ->exists();

            if (!$owns) {
                throw new AuthorizationException('You do not manage this institution.');
            }
        }
    }
}
