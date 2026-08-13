<?php

namespace Tests\Feature\Api;

use App\Models\Flat;
use App\Models\Resident;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ResidentAndFlatTest extends TestCase
{
    use RefreshDatabase;

    private function authenticateAdmin(): User
    {
        $admin = User::create([
            'name' => 'Society Admin',
            'email' => 'admin@example.test',
            'password' => 'Password123!',
            'role' => User::ROLE_ADMIN,
        ]);

        Sanctum::actingAs($admin);

        return $admin;
    }

    public function test_admin_can_create_a_flat(): void
    {
        $this->authenticateAdmin();

        $this->postJson('/api/admin/flats', [
            'flat_number' => '101',
            'building' => 'A',
            'floor' => '1',
            'occupancy_status' => 'VACANT',
        ])->assertCreated()
            ->assertJsonPath('data.flat_number', '101')
            ->assertJsonPath('data.building', 'A');

        $this->assertDatabaseHas('flats', [
            'flat_number' => '101',
            'building' => 'A',
        ]);
    }

    public function test_admin_can_create_a_resident_linked_to_a_flat(): void
    {
        $this->authenticateAdmin();
        $flat = Flat::create([
            'flat_number' => '101',
            'building' => 'A',
            'occupancy_status' => 'OCCUPIED',
        ]);

        $response = $this->postJson('/api/admin/residents', [
            'name' => 'Asha Resident',
            'email' => 'asha@example.test',
            'phone' => '9876543210',
            'password' => 'Password123!',
            'flat_id' => $flat->id,
            'relation_to_owner' => 'Owner',
            'is_primary_contact' => true,
        ]);

        $response->assertCreated()
            ->assertJsonPath('data.user.email', 'asha@example.test')
            ->assertJsonPath('data.flat.id', $flat->id);

        $resident = Resident::query()->with(['user', 'flat'])->firstOrFail();
        $this->assertSame($flat->id, $resident->flat->id);
        $this->assertSame('asha@example.test', $resident->user->email);
        $this->assertTrue($resident->user->hasRole(User::ROLE_RESIDENT));
    }
}
