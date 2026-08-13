<?php

namespace Tests\Feature\Api;

use App\Models\Flat;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AuthenticationTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_can_log_in_view_profile_and_log_out(): void
    {
        $user = User::create([
            'name' => 'Society Admin',
            'email' => 'admin@example.test',
            'password' => 'Password123!',
            'role' => User::ROLE_ADMIN,
        ]);

        $login = $this->postJson('/api/auth/login', [
            'email' => $user->email,
            'password' => 'Password123!',
            'device_name' => 'phpunit',
        ]);

        $login->assertOk()
            ->assertJsonPath('data.user.email', $user->email)
            ->assertJsonMissingPath('data.user.password');

        $token = $login->json('data.token');

        $this->withToken($token)
            ->getJson('/api/auth/me')
            ->assertOk()
            ->assertJsonPath('data.id', $user->id)
            ->assertJsonMissingPath('data.password');

        $this->withToken($token)
            ->postJson('/api/auth/logout')
            ->assertOk();

        $this->assertDatabaseMissing('personal_access_tokens', [
            'tokenable_id' => $user->id,
        ]);
    }

    public function test_invalid_login_is_rejected(): void
    {
        User::create([
            'name' => 'Society Admin',
            'email' => 'admin@example.test',
            'password' => 'Password123!',
            'role' => User::ROLE_ADMIN,
        ]);

        $this->postJson('/api/auth/login', [
            'email' => 'admin@example.test',
            'password' => 'invalid-password',
        ])->assertUnprocessable()
            ->assertJsonPath('message', 'The provided credentials are incorrect.');
    }

    public function test_resident_can_register_only_against_an_existing_flat(): void
    {
        Flat::create([
            'flat_number' => '402',
            'building' => 'H',
            'occupancy_status' => 'VACANT',
        ]);

        $this->postJson('/api/auth/register', [
            'role' => User::ROLE_RESIDENT,
            'name' => 'Asha Resident',
            'email' => 'asha@example.test',
            'phone' => '9876543210',
            'password' => 'Password123!',
            'password_confirmation' => 'Password123!',
            'flat_number' => '402',
            'building' => 'H',
            'date_of_birth' => '1994-03-10',
            'gender' => 'FEMALE',
            'emergency_contact' => '9876543211',
        ])->assertCreated()
            ->assertJsonPath('data.role', User::ROLE_RESIDENT)
            ->assertJsonPath('data.resident.flat_number', '402');

        $this->assertDatabaseHas('users', [
            'email' => 'asha@example.test',
            'role' => User::ROLE_RESIDENT,
        ]);
        $this->assertDatabaseHas('flats', [
            'flat_number' => '402',
            'building' => 'H',
            'occupancy_status' => 'OCCUPIED',
        ]);
    }

    public function test_public_registration_cannot_create_an_admin(): void
    {
        $this->postJson('/api/auth/register', [
            'role' => User::ROLE_ADMIN,
            'name' => 'Public Admin',
            'email' => 'public-admin@example.test',
            'phone' => '9876543210',
            'password' => 'Password123!',
            'password_confirmation' => 'Password123!',
        ])->assertUnprocessable()
            ->assertJsonValidationErrors('role');
    }

    public function test_security_registration_requires_administrator_activation(): void
    {
        $this->postJson('/api/auth/register', [
            'role' => User::ROLE_SECURITY,
            'name' => 'Ravi Guard',
            'email' => 'ravi@example.test',
            'phone' => '9876543210',
            'password' => 'Password123!',
            'password_confirmation' => 'Password123!',
            'employee_id' => 'SEC-101',
            'joining_date' => now()->toDateString(),
            'shift' => 'NIGHT',
            'emergency_contact' => '9876543211',
        ])->assertCreated()
            ->assertJsonPath('data.staff_member.status', 'INACTIVE');

        $this->postJson('/api/auth/login', [
            'login' => '9876543210',
            'password' => 'Password123!',
            'requested_role' => User::ROLE_SECURITY,
        ])->assertForbidden()
            ->assertJsonPath('message', 'Your account is awaiting administrator activation.');
    }

    public function test_authenticated_user_can_update_profile_without_exposing_the_password(): void
    {
        $user = User::create([
            'name' => 'Profile User',
            'email' => 'profile@example.test',
            'phone' => '9876543210',
            'password' => 'Password123!',
            'role' => User::ROLE_STAFF,
        ]);

        $this->actingAs($user, 'sanctum')
            ->patchJson('/api/auth/profile', [
                'name' => 'Updated Profile User',
                'date_of_birth' => '1990-01-02',
                'gender' => 'PREFER_NOT_TO_SAY',
                'emergency_contact' => '9876543211',
                'password' => 'UpdatedPassword123!',
                'password_confirmation' => 'UpdatedPassword123!',
            ])
            ->assertOk()
            ->assertJsonPath('data.name', 'Updated Profile User')
            ->assertJsonPath('data.profile.emergency_contact', '9876543211')
            ->assertJsonMissingPath('data.password');

        $this->assertDatabaseHas('user_profiles', [
            'user_id' => $user->id,
            'gender' => 'PREFER_NOT_TO_SAY',
        ]);
    }
}
