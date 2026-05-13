<?php

namespace App\Http\Controllers\Api\Manager;

use App\Actions\Manager\CreatePrincipalAction;
use App\Http\Controllers\Controller;
use App\Models\Institution;
use Illuminate\Http\Request;

class PrincipalController extends Controller
{
    public function __construct(
        protected CreatePrincipalAction $createPrincipalAction
    ) {}

    public function store(Request $request, $schoolId)
    {
        $school = Institution::where('manager_id', auth()->id())->findOrFail($schoolId);

        if ($school->status !== 'approved') {
            return response()->json([
                'status' => 'error',
                'message' => 'Cannot add a principal to a pending or rejected school.'
            ], 403);
        }

        $data = $request->validate([
            'first_name' => 'required|string|max:255',
            'sur_name' => 'required|string|max:255',
            'email' => 'required|email|unique:users,email',
            'phone_number' => 'required|string|unique:users,phone_number',
            'password' => 'required|string|min:8',
        ]);

        $principal = $this->createPrincipalAction->handle($data, $schoolId);

        return $this->successResponse($principal, 'Principal assigned successfully', 201);
    }
}
