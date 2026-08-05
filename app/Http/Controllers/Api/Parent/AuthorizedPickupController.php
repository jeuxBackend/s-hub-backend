<?php

namespace App\Http\Controllers\Api\Parent;

use App\Http\Controllers\Controller;
use App\Http\Resources\AuthorizedPickupResource;
use App\Models\AuthorizedPickup;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Throwable;

class AuthorizedPickupController extends Controller
{
    public function index()
    {
        try {
            $authorizedPickup = auth()->user()->authorizedPickup;

            return $this->successResponse(
                $authorizedPickup ? new AuthorizedPickupResource($authorizedPickup) : null,
                'Authorized pickup retrieved successfully'
            );
        } catch (Throwable $e) {
            return $this->exceptionResponse($e);
        }
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'first_name' => ['nullable', 'string', 'max:255'],
            'last_name' => ['nullable', 'string', 'max:255'],
            'sur_name' => ['nullable', 'string', 'max:255'],
            'relationship' => ['nullable', 'string', 'max:255'],
            'phone_number' => ['required', 'string', 'max:255'],
            'address' => ['required', 'string', 'max:1000'],
        ]);

        try {
            $parent = auth()->user();

            if ($parent->authorizedPickup()->exists()) {
                throw ValidationException::withMessages([
                    'authorized_pickup' => ['A parent can add only 1 authorized pickup.'],
                ]);
            }

            $authorizedPickup = $parent->authorizedPickup()->create($data);

            return $this->successResponse(
                new AuthorizedPickupResource($authorizedPickup),
                'Authorized pickup created successfully',
                201
            );
        } catch (Throwable $e) {
            return $this->exceptionResponse($e);
        }
    }

    public function update(Request $request, AuthorizedPickup $authorizedPickup)
    {
        $data = $request->validate([
            'first_name' => ['sometimes', 'nullable', 'string', 'max:255'],
            'last_name' => ['sometimes', 'nullable', 'string', 'max:255'],
            'sur_name' => ['sometimes', 'nullable', 'string', 'max:255'],
            'relationship' => ['sometimes', 'nullable', 'string', 'max:255'],
            'phone_number' => ['sometimes', 'string', 'max:255'],
            'address' => ['sometimes', 'string', 'max:1000'],
        ]);

        try {
            if ($authorizedPickup->parent_id !== auth()->id()) {
                throw new AuthorizationException('You are not authorized to manage this authorized pickup.');
            }

            $authorizedPickup->update($data);

            return $this->successResponse(
                new AuthorizedPickupResource($authorizedPickup->fresh()),
                'Authorized pickup updated successfully'
            );
        } catch (Throwable $e) {
            return $this->exceptionResponse($e);
        }
    }

    public function destroy(AuthorizedPickup $authorizedPickup)
    {
        try {
            if ($authorizedPickup->parent_id !== auth()->id()) {
                throw new AuthorizationException('You are not authorized to manage this authorized pickup.');
            }

            $authorizedPickup->delete();

            return $this->successResponse(null, 'Authorized pickup deleted successfully');
        } catch (Throwable $e) {
            return $this->exceptionResponse($e);
        }
    }
}
