<?php

namespace Tests\Feature\Api;

use App\Models\Flat;
use App\Models\Parcel;
use App\Models\Resident;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class SelectionOptionsAndParcelsTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_options_return_actual_owner_and_flat_database_ids(): void
    {
        $admin = $this->user(User::ROLE_ADMIN, 'admin@test.local');
        $owner = $this->user(User::ROLE_RESIDENT, 'owner@test.local');
        $flat = Flat::create(['flat_number' => '402', 'building' => 'H', 'occupancy_status' => 'OCCUPIED']);

        Sanctum::actingAs($admin);

        $this->getJson('/api/admin/options')
            ->assertOk()
            ->assertJsonFragment(['id' => $owner->id, 'label' => 'RESIDENT - owner@test.local'])
            ->assertJsonFragment(['id' => $flat->id, 'label' => 'Flat 402 - Building H']);
    }

    public function test_staff_can_receive_a_parcel_for_a_selected_resident_and_resident_sees_only_their_parcel(): void
    {
        $staff = $this->user(User::ROLE_STAFF, 'staff@test.local');
        [$resident, $flat] = $this->resident('resident@test.local', '402');
        [$otherResident] = $this->resident('other@test.local', '403');

        Sanctum::actingAs($staff);
        $parcelId = $this->postJson('/api/staff/parcels', [
            'flat_id' => $flat->id,
            'resident_id' => $resident->id,
            'courier_name' => 'BlueDart',
            'tracking_number' => 'TRACK-402',
            'parcel_type' => 'BOX',
        ])->assertCreated()->json('data.id');

        Sanctum::actingAs($resident->user);
        $this->getJson('/api/resident/parcels')
            ->assertOk()
            ->assertJsonCount(1, 'data.data')
            ->assertJsonPath('data.data.0.id', $parcelId);

        Sanctum::actingAs($otherResident->user);
        $this->getJson("/api/resident/parcels/{$parcelId}")->assertForbidden();

        Sanctum::actingAs($staff);
        $this->patchJson("/api/staff/parcels/{$parcelId}/collect")
            ->assertOk()
            ->assertJsonPath('data.status', Parcel::STATUS_COLLECTED);
    }

    private function user(string $role, string $email): User
    {
        return User::create([
            'name' => $role,
            'email' => $email,
            'password' => 'Password123!',
            'role' => $role,
        ]);
    }

    private function resident(string $email, string $flatNumber): array
    {
        $user = $this->user(User::ROLE_RESIDENT, $email);
        $flat = Flat::create([
            'flat_number' => $flatNumber,
            'building' => 'H',
            'occupancy_status' => 'OCCUPIED',
        ]);
        $resident = Resident::create(['user_id' => $user->id, 'flat_id' => $flat->id]);

        return [$resident, $flat];
    }
}
