<?php

namespace App\Http\Controllers\Api\Admin;

use App\Enums\AdminRole;
use App\Http\Controllers\Controller;
use App\Http\Resources\AdminResource;
use App\Models\Admin;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;

class AdminProfileController extends Controller
{
    /**
     * Fields each admin-side role may change on their OWN account.
     * Password is handled separately (needs current_password).
     */
    private const EDITABLE = [
        'admin' => ['first_name', 'sure_name', 'last_name', 'phone_number', 'profile_image'],
        'sub_admin' => ['phone_number', 'profile_image'],
        'manager' => ['phone_number', 'emergency_contact_name', 'emergency_contact_phone', 'profile_image'],
    ];

    private const PASSWORD_FIELDS = ['current_password', 'password', 'password_confirmation'];

    public function show()
    {
        $admin = auth()->user();

        if (!$admin instanceof Admin) {
            return $this->errorResponse('Only admin, sub-admin and manager accounts can use this endpoint.', 403);
        }

        return $this->successResponse(['user' => new AdminResource($admin)], 'Profile fetched successfully');
    }

    public function update(Request $request)
    {
        try {
            $admin = auth()->user();

            if (!$admin instanceof Admin) {
                return $this->errorResponse('Only admin, sub-admin and manager accounts can use this endpoint.', 403);
            }

            $roleKey = $admin->role instanceof AdminRole ? $admin->role->value : (string) $admin->role;
            $editable = self::EDITABLE[$roleKey] ?? [];

            // Reject (not silently ignore) anything outside this role's allowed fields.
            $sent = array_keys($request->except(['_method', '_token']));
            $sent = array_merge($sent, array_keys($request->allFiles()));
            $forbidden = array_values(array_diff(array_unique($sent), $editable, self::PASSWORD_FIELDS));

            if (!empty($forbidden)) {
                return $this->errorResponse(
                    'You are not allowed to update: ' . implode(', ', $forbidden) . '. Allowed: ' . implode(', ', array_merge($editable, ['password'])) . '.',
                    422
                );
            }

            $rules = [
                'first_name' => ['sometimes', 'string', 'max:100'],
                'sure_name' => ['sometimes', 'nullable', 'string', 'max:100'],
                'last_name' => ['sometimes', 'string', 'max:100'],
                'phone_number' => ['sometimes', 'string', 'max:50', Rule::unique('admins', 'phone_number')->ignore($admin->id)],
                'emergency_contact_name' => ['sometimes', 'nullable', 'string', 'max:255'],
                'emergency_contact_phone' => ['sometimes', 'nullable', 'string', 'max:255'],
                'profile_image' => ['sometimes', 'image', 'mimes:jpeg,png,jpg,webp', 'max:2048'],
                'password' => ['sometimes', 'string', 'min:6', 'confirmed'],
                'current_password' => ['required_with:password', 'string'],
            ];

            $validated = $request->validate($rules);

            if (isset($validated['password']) && !Hash::check($validated['current_password'], $admin->password)) {
                return $this->errorResponse('Current password is incorrect.', 403);
            }

            $data = array_intersect_key($validated, array_flip($editable));

            if ($request->hasFile('profile_image')) {
                if ($old = $admin->getRawOriginal('profile_image')) {
                    Storage::disk('public')->delete($old);
                }
                $data['profile_image'] = $request->file('profile_image')->store('admin_profiles', 'public');
            }

            if (isset($validated['password'])) {
                $data['password'] = Hash::make($validated['password']);
                $data['password_changed_at'] = now();
            }

            if (!empty($data)) {
                $admin->update($data);
            }

            return $this->successResponse(
                ['user' => new AdminResource($admin->fresh())],
                'Profile updated successfully'
            );
        } catch (\Throwable $th) {
            return $this->exceptionResponse($th);
        }
    }
}
