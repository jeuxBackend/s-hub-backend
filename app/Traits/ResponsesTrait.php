<?php

namespace App\Traits;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\ResourceCollection;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Throwable;
use Illuminate\Support\Facades\Log;

trait ResponsesTrait
{
    protected function successResponse(mixed $data = null, string $message = 'Success', int $code = 200): JsonResponse
    {
        return response()->json([
            'success' => true,
            'message' => $message,
            'data' => $data,
        ], $code);
    }

    protected function errorResponse(string $message = 'Something went wrong', int $code = 400, ?array $errors = null): JsonResponse
    {
        return response()->json([
            'success' => false,
            'message' => $message,
            'errors' => $errors,
        ], $code);
    }

    protected function validationErrorResponse(array $errors, string $message = 'Validation failed'): JsonResponse
    {
        return $this->errorResponse($message, 422, $errors);
    }

    protected function paginatedResponse(ResourceCollection $resource, string $message = 'Success', int $code = 200, array $extraMeta = []): JsonResponse
    {
        return response()->json([
            'success' => true,
            'message' => $message,
            'data' => $resource,
            'meta' => array_merge([
                'pagination' => $this->getPaginationMeta($resource->resource)
            ], $extraMeta),
        ], $code);
    }

    protected function exceptionResponse(Throwable $e): JsonResponse
    {
        $code = $this->getExceptionStatusCode($e);
        $message = $this->getExceptionMessage($e);

        if ($code >= 500 || config('app.debug')) {
            Log::error($e->getMessage(), [
                'exception' => get_class($e),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'trace' => config('app.debug') ? $e->getTraceAsString() : null,
            ]);
        }

        return $this->errorResponse(
            $message,
            $code,
            config('app.debug') ? ['trace' => $e->getMessage()] : null
        );
    }

    private function getPaginationMeta(LengthAwarePaginator $paginator): array
    {
        return [
            'total' => $paginator->total(),
            'per_page' => $paginator->perPage(),
            'current_page' => $paginator->currentPage(),
            'last_page' => $paginator->lastPage(),
            'from' => $paginator->firstItem(),
            'to' => $paginator->lastItem(),
        ];
    }

    private function getExceptionStatusCode(Throwable $e): int
    {
        return match (true) {
            $e instanceof ValidationException => 422,
            $e instanceof AuthorizationException => 403,
            $e instanceof ModelNotFoundException => 404,
            $e instanceof HttpException => $e->getStatusCode(),
            default => 500,
        };
    }

    private function getExceptionMessage(Throwable $e): string
    {
        if (config('app.debug')) {
            return $e->getMessage();
        }

        return match (true) {
            $e instanceof ModelNotFoundException => 'Record not found',
            $e instanceof AuthorizationException => 'You are not authorized to perform this action',
            default => 'Something went wrong',
        };
    }

    protected function createdResponse(mixed $data, string $message = 'Resource created successfully'): JsonResponse
    {
        return $this->successResponse($data, $message, 201);
    }

    protected function noContentResponse(): JsonResponse
    {
        return response()->json(null, 204);
    }
}