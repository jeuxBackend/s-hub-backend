<?php

namespace App\Traits;

use Throwable;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Log;
use Illuminate\Http\Resources\Json\ResourceCollection;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

trait ResponsesTrait
{
    /**
     * Generic success response.
     */
    public function successResponse(mixed $data = null, string $message = 'Success', int $code = 200): JsonResponse
    {
        return response()->json([
            'success' => true,
            'message' => $message,
            'data'    => $data,
        ], $code);
    }

    /**
     * Generic error response.
     */
    public function errorResponse(string $message = 'Something went wrong', int $code = 400): JsonResponse
    {
        return response()->json([
            'success' => false,
            'message' => $message,
            'data'    => null,
        ], $code);
    }

    /**
     * Validation error response.
     */
    public function validationErrorResponse(array $errors, string $message = 'Validation failed', int $code = 422): JsonResponse
    {
        return response()->json([
            'success' => false,
            'message' => $message,
            'errors'  => $errors,
        ], $code);
    }

    /**
     * Paginated response (modern format).
     */
    public function paginatedResponse(ResourceCollection $resource, string $message = 'Success', int $code = 200): JsonResponse
    {
        /** @var LengthAwarePaginator $pagination */
        $pagination = $resource->resource;

        return response()->json([
            'success' => true,
            'message' => $message,
            'data'    => $resource->collection,
            'meta'    => [
                'pagination' => [
                    'total'         => $pagination->total(),
                    'per_page'      => $pagination->perPage(),
                    'current_page'  => $pagination->currentPage(),
                    'last_page'     => $pagination->lastPage(),
                ]
            ],
            'links' => [
                'first' => $pagination->url(1),
                'last'  => $pagination->url($pagination->lastPage()),
                'prev'  => $pagination->previousPageUrl(),
                'next'  => $pagination->nextPageUrl(),
            ],
        ], $code);
    }

    /**
     * Exception response with optional debug detail.
     */
    public function exceptionResponse(Throwable $e, string $message = 'Something went wrong', int $code = 500): JsonResponse
    {
        Log::error('[Exception] ' . $e->getMessage(), [
            'exception' => get_class($e),
            'message'   => $e->getMessage(),
            'file'      => $e->getFile(),
            'line'      => $e->getLine(),
            'trace'     => $e->getTraceAsString(),
        ]);

        return response()->json([
            'success' => false,
            'message' => $message,
            'error'   => config('app.debug') ? $e->getMessage() : null,
        ], $code);
    }
}
