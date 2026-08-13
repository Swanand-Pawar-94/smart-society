<?php

namespace Tests\Feature\Api;

use App\Models\Flat;
use App\Models\MaintenanceBill;
use App\Models\Resident;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class MaintenanceTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_creates_bill_and_resident_only_views_own_bill(): void
    {
        $admin = $this->user(User::ROLE_ADMIN, 'admin@test.local');
        $resident = $this->resident('101', 'resident@test.local');
        $other = $this->resident('102', 'other@test.local');
        Sanctum::actingAs($admin);
        $bill = $this->postJson('/api/admin/maintenance-bills', ['flat_id' => $resident->flat_id, 'billing_month' => '2026-08-01', 'due_date' => '2026-08-15', 'amount' => 1500])->assertOk()->json('data.id');
        $otherBill = MaintenanceBill::create(['flat_id' => $other->flat_id, 'billing_month' => '2026-08-01', 'due_date' => '2026-08-15', 'amount' => 1000, 'status' => MaintenanceBill::STATUS_UNPAID]);
        Sanctum::actingAs($resident->user);
        $this->getJson('/api/resident/maintenance-bills')->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('data.0.id', $bill);
        $this->getJson("/api/resident/maintenance-bills/{$otherBill->id}")->assertForbidden();
    }

    public function test_resident_creates_pending_payment_and_admin_can_complete_it(): void
    {
        $resident = $this->resident('101', 'resident@test.local');
        $bill = MaintenanceBill::create(['flat_id' => $resident->flat_id, 'billing_month' => '2026-08-01', 'due_date' => now()->addDay(), 'amount' => 1500, 'status' => MaintenanceBill::STATUS_UNPAID]);
        Sanctum::actingAs($resident->user);
        $payment = $this->postJson('/api/resident/maintenance-payments', ['maintenance_bill_id' => $bill->id, 'amount' => 1500, 'payment_method' => 'UPI'])->assertOk()->json('data.id');
        $admin = $this->user(User::ROLE_ADMIN, 'admin@test.local');
        Sanctum::actingAs($admin);
        $this->patchJson("/api/admin/maintenance-payments/{$payment}/status", ['status' => 'COMPLETED'])->assertOk();
        $this->assertDatabaseHas('maintenance_bills', ['id' => $bill->id, 'status' => MaintenanceBill::STATUS_PAID]);
    }

    public function test_resident_payment_amount_is_derived_from_the_outstanding_bill(): void
    {
        $resident = $this->resident('101', 'resident@test.local');
        $bill = MaintenanceBill::create([
            'flat_id' => $resident->flat_id,
            'billing_month' => '2026-08-01',
            'due_date' => now()->addDay(),
            'amount' => 1500,
            'status' => MaintenanceBill::STATUS_UNPAID,
        ]);

        Sanctum::actingAs($resident->user);

        $this->postJson('/api/resident/maintenance-payments', [
            'maintenance_bill_id' => $bill->id,
            'amount' => 1,
            'payment_method' => 'UPI',
        ])->assertOk()->assertJsonPath('data.amount', '1500.00');
    }

    public function test_admin_can_create_a_bill_with_a_server_calculated_charge_breakdown(): void
    {
        $admin = $this->user(User::ROLE_ADMIN, 'admin-breakdown@test.local');
        $resident = $this->resident('103', 'breakdown@test.local');
        Sanctum::actingAs($admin);

        $this->postJson('/api/admin/maintenance-bills', [
            'flat_id' => $resident->flat_id,
            'billing_month' => '2026-09-01',
            'billing_period_start' => '2026-09-01',
            'billing_period_end' => '2026-09-30',
            'due_date' => '2026-09-15',
            'base_maintenance' => 1000,
            'water_charge' => 120,
            'electricity_common_area_charge' => 180,
            'parking_charge' => 100,
            'other_charges' => 50,
            'late_fee' => 25,
            'discount' => 75,
        ])->assertOk()
            ->assertJsonPath('data.amount', '1400.00')
            ->assertJsonPath('data.base_maintenance', '1000.00')
            ->assertJsonPath('data.discount', '75.00');
    }

    private function user(string $role, string $email): User
    {
        return User::create(['name' => $role, 'email' => $email, 'password' => 'Password123!', 'role' => $role]);
    }

    private function resident(string $flatNumber, string $email): Resident
    {
        $user = $this->user(User::ROLE_RESIDENT, $email);
        $flat = Flat::create(['flat_number' => $flatNumber, 'building' => 'A', 'occupancy_status' => 'OCCUPIED']);

        return Resident::create(['user_id' => $user->id, 'flat_id' => $flat->id]);
    }
}
