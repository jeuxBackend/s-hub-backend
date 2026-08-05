<?php

namespace App\Http\Controllers\Api\Parent;

use App\Http\Controllers\Controller;
use App\Http\Resources\FamilyMemberResource;
use App\Models\FamilyMember;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Throwable;

class FamilyMemberController extends Controller
{
    public function index()
    {
        try {
            $familyMembers = auth()->user()
                ->familyMembers()
                ->latest()
                ->get();

            return $this->successResponse(
                FamilyMemberResource::collection($familyMembers),
                'Family members retrieved successfully'
            );
        } catch (Throwable $e) {
            return $this->exceptionResponse($e);
        }
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'first_name' => ['required', 'string', 'max:255'],
            'sur_name' => ['required', 'string', 'max:255'],
            'last_name' => ['nullable', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', 'unique:family_members,email', 'unique:users,email'],
            'phone_number' => ['required', 'string', 'max:255', 'unique:family_members,phone_number', 'unique:users,phone_number'],
            'address' => ['nullable', 'string', 'max:1000'],
            'relation_with_parent' => ['required', 'string', 'max:255'],
            'profile_picture' => ['nullable', 'image', 'max:2048'],
            'password' => ['required', 'string', 'min:8'],
        ]);

        try {
            $parent = auth()->user();

            if ($parent->familyMembers()->count() >= 2) {
                throw ValidationException::withMessages([
                    'family_members' => ['A parent can add up to 2 family members only.'],
                ]);
            }

            if ($request->hasFile('profile_picture')) {
                $data['profile_picture'] = $this->handleUserFileUpload($request, 'profile_picture', 'profile_pictures');
            }

            $familyMember = $parent->familyMembers()->create($data);

            return $this->successResponse(
                new FamilyMemberResource($familyMember),
                'Family member created successfully',
                201
            );
        } catch (Throwable $e) {
            return $this->exceptionResponse($e);
        }
    }

    public function destroy(FamilyMember $familyMember)
    {
        try {
            if ($familyMember->parent_id !== auth()->id()) {
                throw new AuthorizationException('You are not authorized to manage this family member.');
            }

            $storedProfilePicture = $familyMember->getRawOriginal('profile_picture');
            if ($storedProfilePicture) {
                Storage::disk('public')->delete($storedProfilePicture);
            }

            $familyMember->delete();

            return $this->successResponse(null, 'Family member deleted successfully');
        } catch (Throwable $e) {
            return $this->exceptionResponse($e);
        }
    }

    public function update(Request $request, FamilyMember $familyMember)
    {
        $data = $request->validate([
            'first_name' => ['sometimes', 'string', 'max:255'],
            'sur_name' => ['sometimes', 'string', 'max:255'],
            'last_name' => ['nullable', 'string', 'max:255'],
            'email' => ['sometimes', 'email', 'max:255', 'unique:family_members,email,' . $familyMember->id, 'unique:users,email'],
            'phone_number' => ['sometimes', 'string', 'max:255', 'unique:family_members,phone_number,' . $familyMember->id, 'unique:users,phone_number'],
            'address' => ['nullable', 'string', 'max:1000'],
            'relation_with_parent' => ['sometimes', 'string', 'max:255'],
            'profile_picture' => ['nullable', 'image', 'max:2048'],
            'password' => ['nullable', 'string', 'min:8'],
        ]);

        try {
            if ($familyMember->parent_id !== auth()->id()) {
                throw new AuthorizationException('You are not authorized to manage this family member.');
            }

            if ($request->hasFile('profile_picture')) {
                $storedProfilePicture = $familyMember->getRawOriginal('profile_picture');
                if ($storedProfilePicture) {
                    Storage::disk('public')->delete($storedProfilePicture);
                }

                $data['profile_picture'] = $this->handleUserFileUpload($request, 'profile_picture', 'profile_pictures');
            }

            if (blank($data['password'] ?? null)) {
                unset($data['password']);
            }

            $familyMember->update($data);

            return $this->successResponse(
                new FamilyMemberResource($familyMember->fresh()),
                'Family member updated successfully'
            );
        } catch (Throwable $e) {
            return $this->exceptionResponse($e);
        }
    }
}
