<?php

namespace Tests\Feature\Api;

use App\Models\Flat;
use App\Models\MaintenanceBill;
use App\Models\MaintenancePayment;
use App\Models\PaymentOrder;
use App\Models\Resident;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class PaymentOrderTest extends TestCase
{
    use RefreshDatabase;

    public function test_resident_creates_one_server_calculated_order_for_all_outstanding_bills(): void
    {
        $user = User::create([
            'name' => 'Resident',
            'email' => 'resident@test.local',
            'password' => 'Password123!',
            'role' => User::ROLE_RESIDENT,
        ]);
        $flat = Flat::create(['flat_number' => '402', 'building' => 'H', 'occupancy_status' => 'OCCUPIED']);
        $resident = Resident::create(['user_id' => $user->id, 'flat_id' => $flat->id]);
        foreach ([1200, 800] as $index => $amount) {
            MaintenanceBill::create([
                'flat_id' => $flat->id,
                'billing_month' => now()->subMonths($index + 1)->startOfMonth(),
                'due_date' => now()->addDay(),
                'amount' => $amount,
                'status' => MaintenanceBill::STATUS_UNPAID,
            ]);
        }

        Sanctum::actingAs($user);

        $this->postJson('/api/resident/payment-orders/full', ['payment_method' => 'UPI'])
            ->assertCreated()
            ->assertJsonPath('data.amount', '2000.00')
            ->assertJsonPath('data.status', PaymentOrder::STATUS_PENDING);

        $this->assertDatabaseCount('payment_orders', 1);
        $this->assertDatabaseCount('payment_order_items', 2);
    }

    public function test_admin_can_verify_a_full_payment_order_and_settle_its_bills(): void
    {
        $resident = $this->residentWithBills();
        Sanctum::actingAs($resident->user);
        $orderId = $this->postJson('/api/resident/payment-orders/full', [
            'payment_method' => MaintenancePayment::METHOD_UPI,
        ])->assertCreated()->json('data.id');

        $admin = User::create([
            'name' => 'Admin',
            'email' => 'admin@test.local',
            'password' => 'Password123!',
            'role' => User::ROLE_ADMIN,
        ]);
        Sanctum::actingAs($admin);

        $this->patchJson("/api/admin/payment-orders/{$orderId}/status", [
            'status' => PaymentOrder::STATUS_SUCCESSFUL,
            'gateway_payment_id' => 'UPI-ORDER-123',
            'verification_note' => 'Verified against the UPI settlement report.',
        ])->assertOk()
            ->assertJsonPath('data.status', PaymentOrder::STATUS_SUCCESSFUL)
            ->assertJsonPath('data.receipt.payment_reference', 'UPI-ORDER-123');

        $this->assertDatabaseCount('maintenance_payments', 2);
        $this->assertDatabaseHas('maintenance_bills', ['flat_id' => $resident->flat_id, 'status' => 'PAID']);
        $this->assertDatabaseHas('payment_orders', [
            'id' => $orderId,
            'status' => PaymentOrder::STATUS_SUCCESSFUL,
            'gateway_payment_id' => 'UPI-ORDER-123',
        ]);
        $this->assertDatabaseHas('payment_receipts', [
            'payment_order_id' => $orderId,
            'payment_reference' => 'UPI-ORDER-123',
        ]);

        Sanctum::actingAs($resident->user);
        $this->getJson("/api/resident/payment-orders/{$orderId}/receipt")
            ->assertOk()
            ->assertJsonPath('data.payment_reference', 'UPI-ORDER-123');
    }

    public function test_resident_cannot_view_or_cancel_another_residents_order(): void
    {
        $owner = $this->residentWithBills();
        Sanctum::actingAs($owner->user);
        $orderId = $this->postJson('/api/resident/payment-orders/full', [
            'payment_method' => MaintenancePayment::METHOD_UPI,
        ])->assertCreated()->json('data.id');

        $otherUser = User::create([
            'name' => 'Other resident',
            'email' => 'other@test.local',
            'password' => 'Password123!',
            'role' => User::ROLE_RESIDENT,
        ]);
        $otherFlat = Flat::create([
            'flat_number' => '403',
            'building' => 'H',
            'occupancy_status' => 'OCCUPIED',
        ]);
        Resident::create(['user_id' => $otherUser->id, 'flat_id' => $otherFlat->id]);
        Sanctum::actingAs($otherUser);

        $this->getJson("/api/resident/payment-orders/{$orderId}")->assertForbidden();
        $this->patchJson("/api/resident/payment-orders/{$orderId}/cancel")->assertForbidden();
    }

    public function test_resident_can_cancel_a_pending_order_and_create_a_replacement(): void
    {
        $resident = $this->residentWithBills();
        Sanctum::actingAs($resident->user);
        $orderId = $this->postJson('/api/resident/payment-orders/full', [
            'payment_method' => MaintenancePayment::METHOD_CARD,
        ])->assertCreated()->json('data.id');

        $this->patchJson("/api/resident/payment-orders/{$orderId}/cancel")
            ->assertOk()
            ->assertJsonPath('data.status', PaymentOrder::STATUS_CANCELLED);

        $this->postJson('/api/resident/payment-orders/full', [
            'payment_method' => MaintenancePayment::METHOD_UPI,
        ])->assertCreated()
            ->assertJsonPath('data.status', PaymentOrder::STATUS_PENDING);
    }

    public function test_payment_summary_is_calculated_only_from_outstanding_bills(): void
    {
        $resident = $this->residentWithBills();
        Sanctum::actingAs($resident->user);

        $this->getJson('/api/resident/payment-summary')
            ->assertOk()
            ->assertJsonPath('data.currency', 'INR')
            ->assertJsonPath('data.other_charges', 0)
            ->assertJsonPath('data.late_fee', 0)
            ->assertJsonPath('data.previous_dues', 2000)
            ->assertJsonPath('data.total_due', 2000)
            ->assertJsonPath('data.bill_count', 2);
    }

    public function test_unconfigured_gateway_never_marks_an_order_as_paid(): void
    {
        $resident = $this->residentWithBills();
        Sanctum::actingAs($resident->user);
        $orderId = $this->postJson('/api/resident/payment-orders/full', [
            'payment_method' => MaintenancePayment::METHOD_UPI,
        ])->assertCreated()->json('data.id');

        $this->postJson("/api/resident/payment-orders/{$orderId}/checkout")
            ->assertStatus(503)
            ->assertJsonPath('code', 'PAYMENT_GATEWAY_NOT_CONFIGURED');

        $this->assertDatabaseHas('payment_orders', [
            'id' => $orderId,
            'status' => PaymentOrder::STATUS_PENDING,
        ]);
        $this->assertDatabaseCount('payment_receipts', 0);
        $this->assertDatabaseCount('maintenance_payments', 0);
    }

    private function residentWithBills(): Resident
    {
        $user = User::create([
            'name' => 'Resident',
            'email' => 'resident'.User::query()->count().'@test.local',
            'password' => 'Password123!',
            'role' => User::ROLE_RESIDENT,
        ]);
        $flat = Flat::create([
            'flat_number' => (string) (402 + Flat::query()->count()),
            'building' => 'H',
            'occupancy_status' => 'OCCUPIED',
        ]);
        $resident = Resident::create(['user_id' => $user->id, 'flat_id' => $flat->id]);
        foreach ([1200, 800] as $index => $amount) {
            MaintenanceBill::create([
                'flat_id' => $flat->id,
                'billing_month' => now()->subMonths($index + 1)->startOfMonth(),
                'due_date' => now()->addDay(),
                'amount' => $amount,
                'status' => MaintenanceBill::STATUS_UNPAID,
            ]);
        }

        return $resident;
    }
}
