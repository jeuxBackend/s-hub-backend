<?php

namespace App\Http\Controllers\Api\Parent;

use App\Http\Controllers\Controller;
use App\Http\Resources\AuthorizedPickupResource;
use App\Models\AuthorizedPickup;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\Request;
use Throwable;

class AuthorizedPickupController extends Controller
{
    public function index()
    {
        try {
            $authorizedPickup = auth()->user()->authorizedPickup;

            return $this->successResponse(
                $authorizedPickup ? new AuthorizedPickupResource($authorizedPickup) : null,
                'Current authorized pickup retrieved successfully'
            );
        } catch (Throwable $e) {
            return $this->exceptionResponse($e);
        }
    }

    /**
     * GET /parent/authorized-pickup/all
     * Every authorized pickup this parent has ever added.
     */
    public function all()
    {
        try {
            $authorizedPickups = auth()->user()->authorizedPickups()->orderByDesc('id')->get();

            return $this->successResponse(
                AuthorizedPickupResource::collection($authorizedPickups),
                'Authorized pickups retrieved successfully'
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

            $authorizedPickup = $parent->authorizedPickups()->create($data);

            // First one ever added automatically becomes the current pickup.
            if (!$parent->current_authorized_pickup_id) {
                $parent->update(['current_authorized_pickup_id' => $authorizedPickup->id]);
            }

            return $this->successResponse(
                new AuthorizedPickupResource($authorizedPickup),
                'Authorized pickup created successfully',
                201
            );
        } catch (Throwable $e) {
            return $this->exceptionResponse($e);
        }
    }

    /**
     * PATCH /parent/authorized-pickup/{authorizedPickup}/set-current
     * Select which of the parent's authorized pickups is the current one.
     */
    public function setCurrent(AuthorizedPickup $authorizedPickup)
    {
        try {
            if ($authorizedPickup->parent_id !== auth()->id()) {
                throw new AuthorizationException('You are not authorized to manage this authorized pickup.');
            }

            auth()->user()->update(['current_authorized_pickup_id' => $authorizedPickup->id]);

            return $this->successResponse(
                new AuthorizedPickupResource($authorizedPickup),
                'Current authorized pickup updated successfully'
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
