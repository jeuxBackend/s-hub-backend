<?php

namespace App\Http\Controllers\Api\Subject;

use App\Http\Controllers\Controller;
use App\Http\Requests\Subject\StoreSubjectRequest;
use App\Http\Requests\Subject\UpdateSubjectRequest;
use App\Http\Resources\SubjectResource;
use App\Actions\Subject\StoreSubjectAction;
use App\Actions\Subject\UpdateSubjectAction;
use App\Actions\Subject\DeleteSubjectAction;
use App\Actions\Subject\ListSubjectsAction;
use Illuminate\Http\Request;
use App\Models\Subject;
use App\Actions\Subject\GetSubjectAction;
use Throwable;

class SubjectController extends Controller
{
    public function index(ListSubjectsAction $listSubjects,Request $request)
    {
        try {
            $requester =auth()->user();
            $filters = $request->only(['name', 'classroom_id', 'code', 'institution_id']);

            $subjects = $listSubjects->handle($requester, $filters);

return $this->paginatedResponse(
    SubjectResource::collection($subjects), // ✅ no new!
    'Subjects list fetched successfully.'
);
        } catch (Throwable $e) {
            return $this->exceptionResponse($e);
        }
    }

    public function show($id, GetSubjectAction $getSubject)
    {
        try {
            $requester = auth()->user();

            $subject = $getSubject->handle($id, $requester);

            return $this->successResponse(
                new SubjectResource($subject),
                'Subject fetched successfully'
            );
        } catch (Throwable $e) {
            return $this->exceptionResponse($e);
        }
    }

    public function store(StoreSubjectRequest $request, StoreSubjectAction $createSubject)
    {
        try {
            $subject = $createSubject->handle($request->validated(), auth()->user());

            return $this->successResponse(
                new SubjectResource($subject),
                'Subject created successfully'
            );
        } catch (Throwable $e) {
            return $this->exceptionResponse($e);
        }
    }

   public function update(UpdateSubjectRequest $request, Subject $subject, UpdateSubjectAction $updateSubject)
{
    try {
        $updated = $updateSubject->handle($subject, $request->validated());

        return $this->successResponse(
            new SubjectResource($updated),
            'Subject updated successfully'
        );
    } catch (Throwable $e) {
        return $this->exceptionResponse($e);
    }
}


   public function destroy(Subject $subject, DeleteSubjectAction $deleteSubject)
{
    try {
        $deleteSubject->handle($subject);

        return $this->successResponse(null, 'Subject deleted successfully');
    } catch (Throwable $e) {
        return $this->exceptionResponse($e);
    }
}
}
