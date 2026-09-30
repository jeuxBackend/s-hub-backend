<?php

namespace App\Http\Controllers\Api\Manager;

use App\Actions\Institution\CreateSchoolAction;
use App\Actions\Institution\DeleteSchoolAction;
use App\Actions\Institution\GetSchoolsAction;
use App\Actions\Institution\UpdateSchoolAction;
use App\Http\Controllers\Controller;
use App\Models\Classroom;
use App\Models\Institution;
use App\Models\Subject;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Validation\Rule;

class SchoolController extends Controller
{
    public function __construct(
        protected GetSchoolsAction $getSchoolsAction,
        protected CreateSchoolAction $createSchoolAction,
        protected UpdateSchoolAction $updateSchoolAction,
        protected DeleteSchoolAction $deleteSchoolAction
    ) {
    }

    public function index(Request $request)
    {
        $data = $request->all();
        $data['manager_id'] = auth()->id();

        $schools = $this->getSchoolsAction->handle($data);
        return $this->paginatedResponse(JsonResource::collection($schools), 'Schools retrieved successfully');
    }

    /**
     * Lightweight list of this manager's own schools' id + name, for dropdowns etc.
     */
    public function names()
    {
        $schools = Institution::where('manager_id', auth()->id())
            ->select(['id', 'name', 'status'])
            ->orderBy('name')
            ->get();

        return $this->successResponse($schools, 'School names retrieved successfully');
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'category_id' => 'required|exists:categories,id',
            'name' => 'required|string|max:255',
            'email' => 'required|email|unique:institutions,email',
            'phone_number' => 'required|string|unique:institutions,phone_number',
            'physical_address' => 'required|string',
            'school_logo' => 'nullable|image|mimes:jpeg,png,jpg,gif|max:2048',

            'slogan' => 'sometimes|nullable|string|max:255',
            'academic_year' => 'sometimes|nullable|string|max:50',
            'examination_system' => 'sometimes|nullable|string|max:255',
            'region' => 'sometimes|nullable|string|max:255',
            'alternate_email' => 'sometimes|nullable|email',
            'alternate_phone' => 'sometimes|nullable|string|max:255',
            'telephone' => 'sometimes|nullable|string|max:255',
            'latitude' => 'sometimes|nullable|numeric|between:-90,90',
            'longitude' => 'sometimes|nullable|numeric|between:-180,180',
            'timezone' => 'sometimes|nullable|string|max:100',
        ]);

        $data['manager_id'] = auth()->id();
        $data['status'] = 'pending';

        $school = $this->createSchoolAction->handle($data);
        return $this->successResponse($school, 'School created successfully', 201);
    }

    public function show($id)
    {
        $school = Institution::with(['manager:id,first_name,sure_name,last_name,email', 'category', 'principal'])
            ->withCount(['students', 'teachers', 'schoolAdmins', 'classrooms'])
            ->where('manager_id', auth()->id())
            ->findOrFail($id);
        return $this->successResponse($school, 'School retrieved successfully');
    }

    /**
     * Id + name of every classroom in one of this manager's own schools —
     * the source list for picking mock_exam_classroom_ids when editing it.
     */
    public function classrooms($id)
    {
        Institution::where('manager_id', auth()->id())->findOrFail($id);

        $classrooms = Classroom::where('institution_id', $id)
            ->select(['id', 'name', 'in_charge_id'])
            ->with('inCharge:id,first_name,last_name,sur_name')
            ->withCount('teachers')
            ->orderBy('name')
            ->get();

        return $this->successResponse($classrooms, 'School classrooms retrieved successfully');
    }

    /**
     * Subjects taught in one classroom — must belong to one of this
     * manager's own schools.
     */
    public function classroomSubjects($id)
    {
        $classroom = Classroom::whereHas('institution', fn ($q) => $q->where('manager_id', auth()->id()))
            ->findOrFail($id);

        $subjects = Subject::where('classroom_id', $classroom->id)
            ->select(['id', 'name', 'code'])
            ->orderBy('name')
            ->get();

        return $this->successResponse($subjects, 'Classroom subjects retrieved successfully');
    }

    public function update(Request $request, $id)
    {
        Institution::where('manager_id', auth()->id())->findOrFail($id);

        $data = $request->validate([
            'name' => 'sometimes|string|max:255',
            'slogan' => 'sometimes|nullable|string|max:255',
            'academic_year' => 'sometimes|nullable|string|max:50',
            'examination_system' => 'sometimes|nullable|string|max:255',
            'physical_address' => 'sometimes|nullable|string',
            'region' => 'sometimes|nullable|string|max:255',

            'email' => 'sometimes|email|unique:institutions,email,' . $id,
            'alternate_email' => 'sometimes|nullable|email',
            'phone_number' => 'sometimes|string|unique:institutions,phone_number,' . $id,
            'alternate_phone' => 'sometimes|nullable|string|max:255',
            'telephone' => 'sometimes|nullable|string|max:255',
            'email_verified' => 'sometimes|boolean',
            'phone_verified' => 'sometimes|boolean',

            'category_id' => 'sometimes|nullable|exists:categories,id',

            'latitude' => 'sometimes|nullable|numeric|between:-90,90',
            'longitude' => 'sometimes|nullable|numeric|between:-180,180',
            'timezone' => 'sometimes|nullable|string|max:100',

            'subjects' => 'sometimes|nullable|array',

            'alert_feature_enabled' => 'sometimes|boolean',
            'allowed_alert_types' => 'nullable|array',
            'allowed_alert_types.*' => 'in:abduction,emergency',
            'mock_exam_classroom_ids' => 'nullable|array',
            'mock_exam_classroom_ids.*' => [
                Rule::exists('classrooms', 'id')->where('institution_id', $id),
            ],

            'school_logo' => 'sometimes|nullable|image|mimes:jpeg,png,jpg,gif|max:2048',
        ]);

        $school = $this->updateSchoolAction->handle($data, $id);
        return $this->successResponse($school, 'School updated successfully');
    }

    public function destroy($id)
    {
        Institution::where('manager_id', auth()->id())->findOrFail($id);
        $this->deleteSchoolAction->handle($id);
        return $this->successResponse(null, 'School deleted successfully');
    }
}
