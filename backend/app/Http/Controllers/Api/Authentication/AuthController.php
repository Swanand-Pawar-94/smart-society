<?php

namespace App\Http\Controllers\Api\Authentication;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\Authentication\LoginRequest;
use App\Http\Requests\Api\Authentication\RegisterRequest;
use App\Http\Requests\Api\Authentication\UpdateAccountProfileRequest;
use App\Http\Resources\Api\Users\UserResource;
use App\Models\Flat;
use App\Models\Resident;
use App\Models\StaffMember;
use App\Models\User;
use App\Models\UserProfile;
use App\Notifications\SocietyAlert;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;

class AuthController extends Controller
{
    public function login(LoginRequest $request): JsonResponse
    {
        $credentials = $request->validated();
        $login = $credentials['login'] ?? $credentials['email'];
        $user = User::query()
            ->where('email', $login)
            ->orWhere('phone', $login)
            ->first();

        if (! $user || ! Hash::check($credentials['password'], $user->password)) {
            return response()->json(['message' => 'The provided credentials are incorrect.'], 422);
        }

        if (($credentials['requested_role'] ?? null) && $credentials['requested_role'] !== $user->role) {
            return response()->json(['message' => 'The selected role does not match this account.'], 403);
        }

        if ($user->hasRole(User::ROLE_STAFF, User::ROLE_SECURITY)
            && $user->staffMember?->status === StaffMember::STATUS_INACTIVE) {
            return response()->json([
                'message' => 'Your account is awaiting administrator activation.',
            ], 403);
        }

        $token = $user->createToken($credentials['device_name'] ?? 'flutter-mobile')->plainTextToken;

        return response()->json([
            'message' => 'Authenticated successfully.',
            'data' => [
                'token' => $token,
                'token_type' => 'Bearer',
                'user' => (new UserResource($user))->resolve(),
            ],
        ]);
    }

    /**
     * Public registration intentionally excludes ADMIN. Staff and security
     * accounts are created inactive, so only an administrator can activate
     * operational access.
     */
    public function register(RegisterRequest $request): JsonResponse
    {
        $data = $request->validated();

        $user = DB::transaction(function () use ($data): User {
            $user = User::create([
                'name' => $data['name'],
                'email' => $data['email'],
                'phone' => $data['phone'],
                'password' => $data['password'],
                'role' => $data['role'],
            ]);

            UserProfile::create([
                'user_id' => $user->id,
                'date_of_birth' => $data['date_of_birth'] ?? null,
                'gender' => $data['gender'] ?? null,
                'emergency_contact' => $data['emergency_contact'] ?? null,
            ]);

            if ($user->hasRole(User::ROLE_RESIDENT)) {
                $flat = Flat::query()
                    ->where('flat_number', $data['flat_number'])
                    ->where('building', $data['building'])
                    ->lockForUpdate()
                    ->first();

                if (! $flat) {
                    abort(Response::HTTP_UNPROCESSABLE_ENTITY, 'The selected flat does not exist.');
                }

                Resident::create([
                    'user_id' => $user->id,
                    'flat_id' => $flat->id,
                    'relation_to_owner' => $data['relation_to_owner'] ?? 'TENANT',
                    'is_primary_contact' => false,
                ]);
                $flat->update(['occupancy_status' => 'OCCUPIED']);
            } else {
                StaffMember::create([
                    'user_id' => $user->id,
                    'employee_id' => $data['employee_id'] ?? null,
                    'name' => $user->name,
                    'mobile' => $user->phone,
                    'email' => $user->email,
                    'designation' => $data['designation'] ?? 'Security Guard',
                    'shift' => $data['shift'] ?? null,
                    'status' => StaffMember::STATUS_INACTIVE,
                    'joining_date' => $data['joining_date'],
                    'emergency_contact' => $data['emergency_contact'] ?? null,
                ]);
            }

            return $user->fresh(['profile', 'resident.flat', 'staffMember']);
        });

        $message = $user->hasRole(User::ROLE_RESIDENT)
            ? 'Registration completed. You can now sign in.'
            : 'Registration received. An administrator must activate your account before you can sign in.';

        User::query()->where('role', User::ROLE_ADMIN)->each(function (User $admin) use ($user): void {
            $admin->notify(new SocietyAlert(
                'account_registered',
                'New account registration',
                sprintf('%s registered as %s.', $user->name, strtolower($user->role)),
                ['user_id' => $user->id, 'role' => $user->role],
            ));
        });

        return response()->json([
            'message' => $message,
            'data' => new UserResource($user),
        ], Response::HTTP_CREATED);
    }

    public function logout(Request $request): JsonResponse
    {
        $request->user()->tokens()->delete();

        return response()->json(['message' => 'Logged out successfully.']);
    }

    public function forgotPassword(Request $request): JsonResponse
    {
        $data = $request->validate(['email' => ['required', 'email']]);
        $status = Password::sendResetLink(['email' => $data['email']]);

        if ($status !== Password::RESET_LINK_SENT) {
            return response()->json([
                'message' => 'Password reset email could not be sent. Please contact society administration.',
            ], 503);
        }

        return response()->json([
            'message' => 'If an account exists, a password reset link has been sent.',
        ], 202);
    }

    public function resetPassword(Request $request): JsonResponse
    {
        $data = $request->validate([
            'token' => ['required', 'string'],
            'email' => ['required', 'email'],
            'password' => ['required', 'confirmed', \Illuminate\Validation\Rules\Password::min(8)->mixedCase()->numbers()],
        ]);

        $status = Password::reset($data, function (User $user, string $password): void {
            $user->forceFill(['password' => $password])->save();
            $user->tokens()->delete();
        });

        if ($status !== Password::PASSWORD_RESET) {
            return response()->json([
                'message' => 'The password reset link is invalid or has expired.',
            ], 422);
        }

        return response()->json(['message' => 'Password updated. Please sign in again.']);
    }

    public function updateProfile(UpdateAccountProfileRequest $request): UserResource
    {
        $user = $request->user();
        $data = $request->validated();
        $user->update(Arr::only($data, ['name', 'email', 'phone', 'password']));
        UserProfile::updateOrCreate(
            ['user_id' => $user->id],
            Arr::only($data, ['date_of_birth', 'gender', 'emergency_contact']),
        );

        return new UserResource($user->fresh(['profile', 'resident.flat', 'staffMember']));
    }

    public function me(Request $request): UserResource
    {
        return new UserResource($request->user()->load(['profile', 'resident.flat', 'staffMember']));
    }
}
