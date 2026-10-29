<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\InstituteType;
use Illuminate\Http\Request;
use Throwable;

class InstituteTypeController extends Controller
{
    /**
     * GET /api/v1/institute-types
     *
     * Retrieve all institute type records.
     * Optional query parameters:
     *   - search (string) — filter types matching substring
     */
    public function index(Request $request)
    {
        try {
            $query = InstituteType::query()->orderBy('id');

            if ($request->filled('search')) {
                $search = trim($request->input('search'));
                $query->where('type', 'like', "%{$search}%");
            }

            $types = $query->get();

            return $this->successResponse(
                $types,
                'Institute types retrieved successfully'
            );
        } catch (Throwable $e) {
            return $this->exceptionResponse($e);
        }
    }

    /**
     * GET /api/v1/institute-types/{id}
     *
     * Retrieve a single institute type by ID.
     */
    public function show($id)
    {
        try {
            $type = InstituteType::findOrFail($id);

            return $this->successResponse(
                $type,
                'Institute type retrieved successfully'
            );
        } catch (Throwable $e) {
            return $this->exceptionResponse($e);
        }
    }
}

