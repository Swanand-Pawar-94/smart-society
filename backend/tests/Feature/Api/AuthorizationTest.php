<?php

namespace Tests\Feature\Api;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class AuthorizationTest extends TestCase
{
    use RefreshDatabase;

    public function test_resident_cannot_access_admin_flat_management(): void
    {
        $resident = User::create([
            'name' => 'Resident User',
            'email' => 'resident@example.test',
            'password' => 'Password123!',
            'role' => User::ROLE_RESIDENT,
        ]);

        Sanctum::actingAs($resident);

        $this->postJson('/api/admin/flats', [
            'flat_number' => '101',
            'building' => 'A',
            'occupancy_status' => 'VACANT',
        ])->assertForbidden();
    }
}
