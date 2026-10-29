<?php

namespace App\Http\Controllers\Api\Admin;

use App\Actions\Admin\GetClassToppersAction;
use App\Enums\TermType;
use App\Http\Controllers\Controller;
use App\Models\Admin;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Throwable;

class ClassToppersController extends Controller
{
    /**
     * GET /api/v1/admin/class-toppers
     *
     * Returns top performing students per classroom per school.
     * Ties are fully included: students sharing identical marks share the same rank.
     *
     * Scoping:
     *   - admin      → can view all schools
     *   - sub_admin  → only schools in their assigned country
     *
     * Query Parameters:
     *   - school_id      (int, optional)    — Filter by school
     *   - institution_id (int, optional)    — Alias for school_id
     *   - classroom_id   (int, optional)    — Filter by classroom
     *   - term           (string, optional) — Filter by term (first, second, third, final)
     *   - limit          (int, optional)    — Number of topper rank positions (e.g. 1 for #1 topper only, default: 3)
     */
    public function index(Request $request, GetClassToppersAction $action)
    {
        try {
            $validated = $request->validate([
                'school_id'      => 'nullable|integer|exists:institutions,id',
                'institution_id' => 'nullable|integer|exists:institutions,id',
                'classroom_id'   => 'nullable|integer|exists:classrooms,id',
                'term'           => ['nullable', 'string', Rule::in(TermType::values())],
                'limit'          => 'nullable|integer|min:1|max:10',
            ]);

            /** @var Admin $admin */
            $admin = auth()->user();

            $data = $action->handle($admin, $validated);

            return $this->successResponse(
                $data,
                'Class toppers fetched successfully'
            );
        } catch (Throwable $e) {
            return $this->exceptionResponse($e);
        }
    }
}
