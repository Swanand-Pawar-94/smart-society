<?php

namespace Tests\Feature\Api;

use App\Models\Flat;
use App\Models\MaintenanceBill;
use App\Models\Resident;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ResidentDashboardTest extends TestCase
{
    use RefreshDatabase;

    public function test_resident_can_view_a_dashboard_for_only_their_unit(): void
    {
        $user = User::create([
            'name' => 'Asha Resident',
            'email' => 'asha@test.local',
            'password' => 'Password123!',
            'role' => User::ROLE_RESIDENT,
        ]);
        $flat = Flat::create([
            'flat_number' => 'A-101',
            'building' => 'A',
            'occupancy_status' => 'OCCUPIED',
        ]);
        $resident = Resident::create(['user_id' => $user->id, 'flat_id' => $flat->id]);
        MaintenanceBill::create([
            'flat_id' => $flat->id,
            'billing_month' => '2026-08-01',
            'due_date' => now()->addDays(3),
            'amount' => 1250,
            'status' => MaintenanceBill::STATUS_UNPAID,
        ]);

        Sanctum::actingAs($resident->user);

        $this->getJson('/api/resident/dashboard')
            ->assertOk()
            ->assertJsonPath('data.resident.name', 'Asha Resident')
            ->assertJsonPath('data.resident.flat_number', 'A-101')
            ->assertJsonPath('data.dues.outstanding_amount', 1250);
    }
}
