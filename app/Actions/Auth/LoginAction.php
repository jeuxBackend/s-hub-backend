<?php

namespace App\Actions\Auth;

use App\Models\User;
use App\Enums\UserRole;
use Illuminate\Support\Facades\Hash;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Validation\ValidationException;
use App\Http\Resources\UserResource;
use App\Http\Resources\InstitutionResource;
use App\Http\Resources\StudentResource;

class LoginAction
{
    public function handle(array $data): array
    {
        $loginField = filter_var($data['login'], FILTER_VALIDATE_EMAIL) ? 'email' : 'phone_number';

        $user = User::where($loginField, $data['login'])->first();

        if (! $user) {
            throw ValidationException::withMessages([
                'login' => 'User not found with provided credentials.',
            ]);
        }

        // ✅ Parents must provide institution_id
        if ($user->role === UserRole::Parent) {
            // if (empty($data['institution_id'])) {
            //     throw ValidationException::withMessages([
            //         'institution_id' => 'Institution ID is required for parent login.',
            //     ]);
            // }
            // (Optional) You can validate institution_id match
        }

        // ✅ School Admin must belong to an institution
        if ($user->role === UserRole::SchoolAdmin) {
            if (! $user->creator || ! $user->creator->institution) {
                throw new AuthorizationException('School admin is not assigned to any institution.');
            }

            if (isset($data['institution_id']) && $data['institution_id'] != $user->creator->institution->id) {
                throw new AuthorizationException('School admin is not associated with this institution.');
            }
        }

        // ✅ OTP verification (for all except parents)
        if (! $user->otp_verified && $user->role !== UserRole::Parent) {
            throw new AuthorizationException('Your account is not verified via OTP.');
        }

        // ✅ Block check
        if (! $user->status) {
            throw new AuthorizationException('Your account has been blocked.');
        }

        // ✅ Password check
        if (! Hash::check($data['password'], $user->password)) {
            throw ValidationException::withMessages([
                'password' => 'Incorrect password.',
            ]);
        }

        // ✅ Update device info
        $user->update([
            'device_id' => $data['device_id'] ?? $user->device_id,
            'fcm_token' => $data['fcm_token'] ?? $user->fcm_token,
        ]);

        // ✅ Create token
        $token = $user->createToken('auth_token')->plainTextToken;

        // ✅ Enrich response based on role
        $extra = [];

        if ($user->role === UserRole::Principal) {
            $user->load('institution');

            $teachers = User::where('role', UserRole::Teacher)
                ->where('created_by', $user->id)
                ->get();

            $extra['institution'] = new InstitutionResource($user->institution);
            $extra['teachers'] = UserResource::collection($teachers);
        }
         if ($user->role === UserRole::Parent) {
            $user->load('guardianStudents');
            $user->guardianStudents=StudentResource::collection($user->guardianStudents);
            // $teachers = User::where('role', UserRole::Teacher)
            //     ->where('created_by', $user->id)
            //     ->get();

            // $extra['institution'] = new InstitutionResource($user->institution);
            // $extra['teachers'] = UserResource::collection($teachers);
        }

        if ($user->role === UserRole::SchoolAdmin) {
            $user->load('creator.institution');

            $teachers = User::where('role', UserRole::Teacher)
                ->where('created_by', $user->created_by)
                ->get();

            $extra['institution'] = new InstitutionResource(optional($user->creator)->institution);
            $extra['teachers'] = UserResource::collection($teachers);
        }

        // ✅ Final response
        return [
            'user'  => new UserResource($user),
            'token' => $token,
            // ...$extra,
        ];
    }
}
