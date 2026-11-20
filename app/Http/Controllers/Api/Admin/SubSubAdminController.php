<?php

namespace App\Http\Controllers\Api\Admin;

use App\Enums\AdminRole;
use App\Enums\SubAdminPermission;
use App\Http\Controllers\Controller;
use App\Http\Resources\AdminResource;
use App\Models\Admin;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * A sub-admin creates and manages their own sub-sub-admins. Sub-admin only
 * (see routes). A sub-sub-admin works on the same schools as its creator and
 * can only hold permissions that its creator holds.
 */
class SubSubAdminController extends Controller
{
    private function own($id): Admin
    {
        return Admin::where('role', AdminRole::SubSubAdmin->value)
            ->where('parent_id', auth()->id())
            ->findOrFail($id);
    }

    /**
     * Permissions the calling sub-admin can hand out (the ones they hold).
     */
    private function grantable(): array
    {
        $own = is_array(auth()->user()->permissions) ? auth()->user()->permissions : [];

        return array_values(array_intersect(SubAdminPermission::values(), $own));
    }

    public function permissions()
    {
        return $this->successResponse(
            array_map(fn ($p) => ['value' => $p, 'label' => $p], $this->grantable()),
            'Sub-sub-admin permissions retrieved successfully'
        );
    }

    public function index(Request $request)
    {
        $query = Admin::where('role', AdminRole::SubSubAdmin->value)
            ->where('parent_id', auth()->id())
            ->when($request->filled('search'), function ($q) use ($request) {
                $s = $request->input('search');
                $q->where(function ($qq) use ($s) {
                    $qq->where('first_name', 'like', "%{$s}%")
                        ->orWhere('last_name', 'like', "%{$s}%")
                        ->orWhere('sure_name', 'like', "%{$s}%")
                        ->orWhere('email', 'like', "%{$s}%")
                        ->orWhere('phone_number', 'like', "%{$s}%");
                });
            })
            ->when($request->filled('status'), fn ($q) => $q->where('status', $request->input('status')))
            ->tap(fn ($q) => \App\Support\LocationFilter::apply($q, $request->all()))
            ->orderByDesc('id');

        return $this->paginatedResponse(
            AdminResource::collection($query->paginate($request->input('per_page', 20))),
            'Sub-sub-admins retrieved successfully'
        );
    }

    public function show($id)
    {
        return $this->successResponse(new AdminResource($this->own($id)), 'Sub-sub-admin retrieved successfully');
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'first_name' => 'required|string|max:255',
            'sure_name' => 'required|string|max:255',
            'last_name' => 'nullable|string|max:255',
            'email' => ['required', 'email', Rule::unique('admins', 'email'), Rule::unique('users', 'email')],
            'phone_number' => 'required|string|unique:admins,phone_number',
            'password' => 'required|string|min:8',
            'state' => 'nullable|string|max:100',
            'province' => 'nullable|string|max:100',
            'city' => 'nullable|string|max:100',
            'profile_image' => 'nullable|image|mimes:jpeg,png,jpg,webp|max:2048',
            'permissions' => 'nullable|array',
            'permissions.*' => 'string|distinct',
        ]);

        $data['permissions'] = $this->validatedPermissions($data['permissions'] ?? []);

        if ($request->hasFile('profile_image')) {
            $data['profile_image'] = $request->file('profile_image')->store('admin_profiles', 'public');
        }

        $data['password'] = Hash::make($data['password']);
        $data['role'] = AdminRole::SubSubAdmin->value;
        $data['parent_id'] = auth()->id();
        $data['status'] = 'active';

        $subSubAdmin = Admin::create($data);
        app(\App\Services\AdminNotificationService::class)->welcome($subSubAdmin, trim(auth()->user()->first_name . ' ' . auth()->user()->last_name));

        return $this->successResponse(new AdminResource($subSubAdmin->fresh()), 'Sub-sub-admin created successfully', 201);
    }

    public function update(Request $request, $id)
    {
        $subSubAdmin = $this->own($id);

        $data = $request->validate([
            'first_name' => 'sometimes|string|max:255',
            'sure_name' => 'sometimes|string|max:255',
            'last_name' => 'sometimes|nullable|string|max:255',
            'email' => ['sometimes', 'email', Rule::unique('admins', 'email')->ignore($subSubAdmin->id), Rule::unique('users', 'email')],
            'phone_number' => 'sometimes|string|unique:admins,phone_number,' . $subSubAdmin->id,
            'password' => 'sometimes|string|min:8',
            'state' => 'sometimes|nullable|string|max:100',
            'province' => 'sometimes|nullable|string|max:100',
            'city' => 'sometimes|nullable|string|max:100',
            'profile_image' => 'sometimes|image|mimes:jpeg,png,jpg,webp|max:2048',
            'permissions' => 'sometimes|array',
            'permissions.*' => 'string|distinct',
        ]);

        // Keep the same email without tripping the users-table uniqueness check.
        if (isset($data['email']) && strcasecmp($data['email'], $subSubAdmin->email) === 0) {
            unset($data['email']);
        }

        if (array_key_exists('permissions', $data)) {
            $data['permissions'] = $this->validatedPermissions($data['permissions']);
        }

        if ($request->hasFile('profile_image')) {
            if ($old = $subSubAdmin->getRawOriginal('profile_image')) {
                Storage::disk('public')->delete($old);
            }
            $data['profile_image'] = $request->file('profile_image')->store('admin_profiles', 'public');
        }

        if (!empty($data['password'])) {
            $data['password'] = Hash::make($data['password']);
            $data['password_changed_at'] = now();
        }

        if (!empty($data)) {
            $subSubAdmin->update($data);
        }

        return $this->successResponse(new AdminResource($subSubAdmin->fresh()), 'Sub-sub-admin updated successfully');
    }

    public function toggleBlock($id)
    {
        $subSubAdmin = $this->own($id);
        $blocking = $subSubAdmin->status === 'active';

        $subSubAdmin->update(['status' => $blocking ? 'inactive' : 'active']);

        app(\App\Services\AdminNotificationService::class)->accountStatusChanged($subSubAdmin, $blocking);

        if ($blocking) {
            $subSubAdmin->tokens()->delete();
        }

        return $this->successResponse(
            new AdminResource($subSubAdmin->fresh()),
            $blocking ? 'Sub-sub-admin blocked successfully' : 'Sub-sub-admin unblocked successfully'
        );
    }

    public function destroy($id)
    {
        $subSubAdmin = $this->own($id);

        if ($old = $subSubAdmin->getRawOriginal('profile_image')) {
            Storage::disk('public')->delete($old);
        }

        $subSubAdmin->tokens()->delete();
        $subSubAdmin->delete();

        return $this->successResponse(null, 'Sub-sub-admin deleted successfully');
    }

    /**
     * Every requested permission must be a known one that the calling
     * sub-admin holds themselves.
     */
    private function validatedPermissions(array $requested): array
    {
        $requested = array_values(array_unique($requested));
        $allowed = $this->grantable();
        $invalid = array_values(array_diff($requested, $allowed));

        if (!empty($invalid)) {
            throw ValidationException::withMessages([
                'permissions' => ['You cannot grant: ' . implode(', ', $invalid) . '. You can only grant permissions you hold: ' . (implode(', ', $allowed) ?: 'none') . '.'],
            ]);
        }

        return $requested;
    }
}
