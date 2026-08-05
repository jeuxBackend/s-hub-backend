<?php

namespace App\Actions\Auth;

use App\Models\User;
use App\Enums\UserRole;
use App\Enums\GuardianType;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Http\UploadedFile;
use App\Actions\Auth\SendOtpAction;

class SignupAction
{
    public function handle(array $data): array
    {
        $actingUser = Auth::user();
        $isSelfSignup = $actingUser === null;
        $requestedRole = UserRole::from($data['role']);

        // ✅ Only principals can sign up directly
        if ($isSelfSignup && $requestedRole !== UserRole::Principal) {
            abort(403, 'Only principals can sign up directly.');
        }

        // ✅ Role-based creation permissions
        if (! $isSelfSignup) {
           if ($requestedRole === UserRole::SubAdmin && $actingUser->role !== UserRole::Admin) {
                abort(403, 'Only admins can create sub-admin accounts.');
            }

            if (
                in_array($requestedRole, [UserRole::Teacher, UserRole::Parent, UserRole::SchoolAdmin], true) &&
                $actingUser->role !== UserRole::Principal
            ) {
                abort(403, 'Only principals can create teacher, school admin, or parent accounts.');
            }

            if ($requestedRole === UserRole::Principal) {
                abort(403, 'Only principals can sign up themselves.');
            }
        }

        // ✅ Upload profile picture
        if (!empty($data['profile_picture']) && $data['profile_picture'] instanceof UploadedFile) {
            $data['profile_picture'] = $data['profile_picture']->store('profile_pictures', 'public');
        }

        // ✅ Secure password
        $data['password'] = Hash::make($data['password']);

        // ✅ Create user
        $user = User::create([
            'email'                         => $data['email'],
            'phone_number'                  => $data['phone_number'],
            'password'                      => $data['password'],
            'role'                          => $requestedRole,
            'first_name'                    => $data['first_name'] ?? null,
            'last_name'                     => $data['last_name'] ?? null,
            'sur_name'                      => $data['sur_name'] ?? null,
            'title'                         => $data['title'] ?? null,
            'position'                      => $data['position'] ?? null,
            'staff_number'                  => $data['staff_number'] ?? null,
            'guardian_type'                 => isset($data['guardian_type']) ? GuardianType::from($data['guardian_type']) : null,
            'guardian_name'                 => $data['guardian_name'] ?? null,
            'guardian_relation'             => $data['guardian_relation'] ?? null,
            'guardian_phone_number'         => $data['guardian_phone_number'] ?? null,
            'alternative_guardian_phone_number' => $data['alternative_guardian_phone_number'] ?? null,
            'alternative_email'             => $data['alternative_email'] ?? null,
            'alternative_phone_number'      => $data['alternative_phone_number'] ?? null,
            'device_id'                     => $data['device_id'] ?? null,
            'fcm_token'                     => $data['fcm_token'] ?? null,
            'profile_picture'               => $data['profile_picture'] ?? null,
            'created_by'                    => $actingUser?->id,
            'otp_verified'                  => ! $isSelfSignup,
            'status'                        => true,
            'permissions'                   => $requestedRole === UserRole::SchoolAdmin ? ($data['permissions'] ?? []) : null,
            'security_question'             => $data['security_question'] ?? null,
            'answer_security_question'      => isset($data['answer_security_question']) ? Hash::make($data['answer_security_question']) : null,

        ]);

        $user->refresh();

        // ✅ If principal, create their institution
        if ($isSelfSignup && $requestedRole === UserRole::Principal) {
            if (!empty($data['logo']) && $data['logo'] instanceof UploadedFile) {
                $data['logo'] = $data['logo']->store('institution_logos', 'public');
            }

            $emailAndPhoneMatch =
                isset($data['institution_email'], $data['email'], $data['institution_phone_number'], $data['phone_number']) &&
                $data['institution_email'] === $data['email'] &&
                $data['institution_phone_number'] === $data['phone_number'];

            $user->institution()->create([
                'category_id'        => $data['category_id'] ?? null,
                'name'               => $data['institution_name'],
                'slogan'             => $data['slogan'] ?? null,
                'logo'               => $data['logo'] ?? null,
                'academic_year'      => $data['academic_year'] ?? null,
                'examination_system' => $data['examination_system'] ?? null,
                'physical_address'   => $data['physical_address'] ?? null,
                'email'              => $data['institution_email'],
                'alternate_email'    => $data['institution_alternate_email'] ?? null,
                'phone_number'       => $data['institution_phone_number'],
                'alternate_phone'    => $data['institution_alternate_phone'] ?? null,
                'telephone'          => $data['institution_telephone'] ?? null,
                'email_verified'     => $emailAndPhoneMatch,
                'phone_verified'     => $emailAndPhoneMatch,
                'subjects'           => $data['subjects'] ?? [],
            ]);
        }

        // ✅ Send OTP to principal if self-signing up
        $response = ['user' => $user];

        if ($isSelfSignup && $requestedRole === UserRole::Principal) {
            $otp = app(SendOtpAction::class)->handle([
                'type'         => 'email',
                'email'        => $user->email,
                'phone_number' => $user->phone_number,
            ]);

            if (app()->environment(['local', 'testing']) && isset($otp['code'])) {
                $response['otp_code'] = $otp['code'];
            }
        }

        return $response;
    }
}
