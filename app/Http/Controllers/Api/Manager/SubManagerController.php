<?php

namespace App\Http\Controllers\Api\Manager;

use App\Enums\AdminRole;
use App\Http\Controllers\Controller;
use App\Http\Resources\AdminResource;
use App\Models\Admin;
use App\Models\Institution;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * A manager creates and manages their own sub-managers and decides which of
 * their schools each one looks after. Manager-only (see routes).
 */
class SubManagerController extends Controller
{
    private function own($id): Admin
    {
        return Admin::where('role', AdminRole::SubManager->value)
            ->where('manager_id', auth()->id())
            ->with('subManagerSchools:id,name,sub_manager_id')
            ->findOrFail($id);
    }

    public function index(Request $request)
    {
        $query = Admin::where('role', AdminRole::SubManager->value)
            ->where('manager_id', auth()->id())
            ->with('subManagerSchools:id,name,sub_manager_id')
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
            'Sub-managers retrieved successfully'
        );
    }

    public function show($id)
    {
        return $this->successResponse(new AdminResource($this->own($id)), 'Sub-manager retrieved successfully');
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
            'emergency_contact_name' => 'nullable|string|max:255',
            'emergency_contact_phone' => 'nullable|string|max:255',
            'profile_image' => 'nullable|image|mimes:jpeg,png,jpg,webp|max:2048',
            'school_ids' => 'nullable|array',
            'school_ids.*' => 'integer|distinct',
        ]);

        $schoolIds = array_map('intval', $data['school_ids'] ?? []);
        $this->assertAssignable($schoolIds, null);
        unset($data['school_ids']);

        if ($request->hasFile('profile_image')) {
            $data['profile_image'] = $request->file('profile_image')->store('admin_profiles', 'public');
        }

        $data['password'] = Hash::make($data['password']);
        $data['role'] = AdminRole::SubManager->value;
        $data['manager_id'] = auth()->id();
        $data['status'] = 'active';

        $subManager = DB::transaction(function () use ($data, $schoolIds) {
            $subManager = Admin::create($data);
            $this->syncSchools($subManager, $schoolIds);

            return $subManager;
        });

        app(\App\Services\AdminNotificationService::class)->welcome($subManager, trim(auth()->user()->first_name . ' ' . auth()->user()->last_name));

        return $this->successResponse(
            new AdminResource($subManager->load('subManagerSchools:id,name,sub_manager_id')),
            'Sub-manager created successfully',
            201
        );
    }

    public function update(Request $request, $id)
    {
        $subManager = $this->own($id);

        $data = $request->validate([
            'first_name' => 'sometimes|string|max:255',
            'sure_name' => 'sometimes|string|max:255',
            'last_name' => 'sometimes|nullable|string|max:255',
            'email' => ['sometimes', 'email', Rule::unique('admins', 'email')->ignore($subManager->id), Rule::unique('users', 'email')],
            'phone_number' => 'sometimes|string|unique:admins,phone_number,' . $subManager->id,
            'password' => 'sometimes|string|min:8',
            'state' => 'sometimes|nullable|string|max:100',
            'province' => 'sometimes|nullable|string|max:100',
            'city' => 'sometimes|nullable|string|max:100',
            'emergency_contact_name' => 'sometimes|nullable|string|max:255',
            'emergency_contact_phone' => 'sometimes|nullable|string|max:255',
            'profile_image' => 'sometimes|image|mimes:jpeg,png,jpg,webp|max:2048',
            'school_ids' => 'sometimes|array',
            'school_ids.*' => 'integer|distinct',
        ]);

        // Keep the same email without tripping the users-table uniqueness check.
        if (isset($data['email']) && strcasecmp($data['email'], $subManager->email) === 0) {
            unset($data['email']);
        }

        $schoolIds = null;
        if (array_key_exists('school_ids', $data)) {
            $schoolIds = array_map('intval', $data['school_ids']);
            $this->assertAssignable($schoolIds, $subManager->id);
        }
        unset($data['school_ids']);

        if ($request->hasFile('profile_image')) {
            if ($old = $subManager->getRawOriginal('profile_image')) {
                Storage::disk('public')->delete($old);
            }
            $data['profile_image'] = $request->file('profile_image')->store('admin_profiles', 'public');
        }

        if (!empty($data['password'])) {
            $data['password'] = Hash::make($data['password']);
            $data['password_changed_at'] = now();
        }

        DB::transaction(function () use ($subManager, $data, $schoolIds) {
            if (!empty($data)) {
                $subManager->update($data);
            }
            if ($schoolIds !== null) {
                $this->syncSchools($subManager, $schoolIds);
            }
        });

        return $this->successResponse(
            new AdminResource($subManager->fresh()->load('subManagerSchools:id,name,sub_manager_id')),
            'Sub-manager updated successfully'
        );
    }

    public function toggleBlock($id)
    {
        $subManager = $this->own($id);
        $blocking = $subManager->status === 'active';

        $subManager->update(['status' => $blocking ? 'inactive' : 'active']);

        app(\App\Services\AdminNotificationService::class)->accountStatusChanged($subManager, $blocking);

        if ($blocking) {
            $subManager->tokens()->delete();
        }

        return $this->successResponse(
            new AdminResource($subManager->fresh()->load('subManagerSchools:id,name,sub_manager_id')),
            $blocking ? 'Sub-manager blocked successfully' : 'Sub-manager unblocked successfully'
        );
    }

    public function destroy($id)
    {
        $subManager = $this->own($id);

        DB::transaction(function () use ($subManager) {
            // Schools go back to the manager; nothing created in them is touched.
            Institution::where('sub_manager_id', $subManager->id)->update(['sub_manager_id' => null]);

            if ($old = $subManager->getRawOriginal('profile_image')) {
                Storage::disk('public')->delete($old);
            }

            $subManager->tokens()->delete();
            $subManager->delete();
        });

        return $this->successResponse(null, 'Sub-manager deleted successfully');
    }

    /**
     * Every school must belong to the calling manager and not be held by a
     * different sub-manager.
     */
    private function assertAssignable(array $schoolIds, ?int $subManagerId): void
    {
        if (empty($schoolIds)) {
            return;
        }

        $schools = Institution::where('manager_id', auth()->id())->whereIn('id', $schoolIds)->get(['id', 'name', 'sub_manager_id']);

        $foreign = array_values(array_diff($schoolIds, $schools->pluck('id')->all()));
        if (!empty($foreign)) {
            throw ValidationException::withMessages([
                'school_ids' => ['These schools are not yours: ' . implode(', ', $foreign) . '.'],
            ]);
        }

        $taken = $schools->filter(fn ($s) => $s->sub_manager_id !== null && (int) $s->sub_manager_id !== $subManagerId);
        if ($taken->isNotEmpty()) {
            throw ValidationException::withMessages([
                'school_ids' => ['Already assigned to another sub-manager: ' . $taken->pluck('name')->implode(', ') . '.'],
            ]);
        }
    }

    /**
     * Make $schoolIds exactly the set of schools assigned to this sub-manager.
     */
    private function syncSchools(Admin $subManager, array $schoolIds): void
    {
        Institution::where('sub_manager_id', $subManager->id)
            ->whereNotIn('id', $schoolIds)
            ->update(['sub_manager_id' => null]);

        if (!empty($schoolIds)) {
            Institution::where('manager_id', auth()->id())
                ->whereIn('id', $schoolIds)
                ->update(['sub_manager_id' => $subManager->id]);
        }
    }
}
