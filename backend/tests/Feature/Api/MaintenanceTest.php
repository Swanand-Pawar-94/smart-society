<?php

namespace Tests\Feature\Api;

use App\Models\Flat;
use App\Models\MaintenanceBill;
use App\Models\MaintenancePayment;
use App\Models\Resident;
use App\Models\User;
use App\Services\Maintenance\MaintenanceService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
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

    public function test_command_generates_a_specified_month_once_with_the_real_rules(): void
    {
        $eligible = $this->resident('201', 'eligible@test.local');
        $vacantUser = $this->user(User::ROLE_RESIDENT, 'vacant@test.local');
        $vacantFlat = Flat::create([
            'flat_number' => '202',
            'building' => 'A',
            'occupancy_status' => 'VACANT',
        ]);
        Resident::create(['user_id' => $vacantUser->id, 'flat_id' => $vacantFlat->id]);

        $this->artisan('maintenance:generate', ['--month' => '2026-09'])
            ->expectsOutput('Generating maintenance for September 2026...')
            ->expectsOutput('1 bills created')
            ->expectsOutput('0 bills already existed')
            ->assertExitCode(0);

        $bill = MaintenanceBill::query()
            ->where('flat_id', $eligible->flat_id)
            ->whereDate('billing_month', '2026-09-01')
            ->firstOrFail();
        $this->assertSame('2026-09-01', $bill->billing_month->toDateString());
        $this->assertSame('2026-09-16', $bill->due_date->toDateString());
        $this->assertSame(2000.0, (float) $bill->amount);
        $this->assertSame(2000.0, (float) $bill->base_maintenance);
        $this->assertSame(MaintenanceBill::STATUS_UNPAID, $bill->status);
        $this->assertDatabaseMissing('maintenance_bills', [
            'flat_id' => $vacantFlat->id,
            'billing_month' => '2026-09-01',
        ]);

        $this->artisan('maintenance:generate', ['--month' => '2026-09'])
            ->expectsOutput('0 bills created')
            ->expectsOutput('1 bills already existed')
            ->assertExitCode(0);

        $this->assertDatabaseCount('maintenance_bills', 1);
    }

    public function test_default_generation_uses_the_current_month_without_changing_the_clock(): void
    {
        $resident = $this->resident('301', 'next-month@test.local');
        Carbon::setTestNow('2026-10-01 00:00:00');

        try {
            app(MaintenanceService::class)->generateMonthlyInvoices();
        } finally {
            Carbon::setTestNow();
        }

        $bill = MaintenanceBill::query()
            ->where('flat_id', $resident->flat_id)
            ->whereDate('billing_month', '2026-10-01')
            ->firstOrFail();
        $this->assertSame('2026-10-01', $bill->billing_month->toDateString());
        $this->assertSame('2026-10-16', $bill->due_date->toDateString());
        $this->assertSame(2000.0, (float) $bill->amount);
        $this->assertSame(MaintenanceBill::STATUS_UNPAID, $bill->status);
    }

    public function test_resident_can_create_a_payment_order_for_only_selected_own_invoices(): void
    {
        $resident = $this->resident('401', 'selected-order@test.local');
        $ownFirst = MaintenanceBill::create([
            'flat_id' => $resident->flat_id, 'billing_month' => '2026-08-01',
            'due_date' => '2026-08-15', 'amount' => 1000, 'status' => MaintenanceBill::STATUS_UNPAID,
        ]);
        $ownSecond = MaintenanceBill::create([
            'flat_id' => $resident->flat_id, 'billing_month' => '2026-09-01',
            'due_date' => '2026-09-16', 'amount' => 1500, 'status' => MaintenanceBill::STATUS_UNPAID,
        ]);
        $other = $this->resident('402', 'other-selected@test.local');
        $otherBill = MaintenanceBill::create([
            'flat_id' => $other->flat_id, 'billing_month' => '2026-08-01',
            'due_date' => '2026-08-15', 'amount' => 900, 'status' => MaintenanceBill::STATUS_UNPAID,
        ]);

        Sanctum::actingAs($resident->user);
        $this->postJson('/api/resident/payment-orders/full', [
            'payment_method' => 'UPI',
            'maintenance_bill_ids' => [$ownSecond->id],
        ])->assertCreated()
            ->assertJsonPath('data.amount', '1500.00')
            ->assertJsonCount(1, 'data.items')
            ->assertJsonPath('data.items.0.maintenance_bill_id', $ownSecond->id);

        $this->postJson('/api/resident/payment-orders/full', [
            'payment_method' => 'UPI',
            'maintenance_bill_ids' => [$ownFirst->id, $otherBill->id],
        ])->assertUnprocessable()
            ->assertJsonValidationErrors('maintenance_bill_ids');
    }

    public function test_resident_invoice_view_returns_server_calculated_document_data(): void
    {
        $resident = $this->resident('501', 'invoice-view@test.local');
        $previous = MaintenanceBill::create([
            'flat_id' => $resident->flat_id,
            'billing_month' => '2026-07-01',
            'due_date' => '2026-07-16',
            'amount' => 100,
            'status' => MaintenanceBill::STATUS_PARTIALLY_PAID,
        ]);
        MaintenancePayment::create([
            'maintenance_bill_id' => $previous->id,
            'resident_id' => $resident->id,
            'amount' => 25,
            'reference_id' => 'PAST-PAYMENT',
            'payment_method' => MaintenancePayment::METHOD_UPI,
            'status' => MaintenancePayment::STATUS_COMPLETED,
            'paid_at' => now(),
        ]);
        $current = MaintenanceBill::create([
            'flat_id' => $resident->flat_id,
            'billing_month' => '2026-08-01',
            'billing_period_start' => '2026-08-01',
            'billing_period_end' => '2026-08-31',
            'due_date' => '2026-08-16',
            'base_maintenance' => 1000,
            'late_fee' => 25,
            'amount' => 1025,
            'status' => MaintenanceBill::STATUS_UNPAID,
        ]);

        Sanctum::actingAs($resident->user);

        $this->getJson("/api/resident/maintenance-bills/{$current->id}")
            ->assertOk()
            ->assertJsonPath('data.id', $current->id)
            ->assertJsonPath('data.invoice.invoice_number', 'INV-202608-'.str_pad((string) $current->id, 6, '0', STR_PAD_LEFT))
            ->assertJsonPath('data.invoice.resident.name', $resident->user->name)
            ->assertJsonPath('data.invoice.previous_dues', 75)
            ->assertJsonPath('data.invoice.current_total', 1025)
            ->assertJsonPath('data.invoice.net_payable', 1100)
            ->assertJsonPath('data.invoice.line_items.1.account', 'Late Payment Charges');
    }

    public function test_single_invoice_payment_in_demo_mode_settles_only_selected_invoice(): void
    {
        config(['app.payment_mode' => 'demo']);

        $resident = $this->resident('601', 'demo-single-pay@test.local');
        $augustBill = MaintenanceBill::create([
            'flat_id' => $resident->flat_id,
            'billing_month' => '2026-08-01',
            'due_date' => '2026-08-15',
            'amount' => 2000,
            'status' => MaintenanceBill::STATUS_UNPAID,
        ]);
        $septemberBill = MaintenanceBill::create([
            'flat_id' => $resident->flat_id,
            'billing_month' => '2026-09-01',
            'due_date' => '2026-09-15',
            'amount' => 2000,
            'status' => MaintenanceBill::STATUS_UNPAID,
        ]);

        Sanctum::actingAs($resident->user);

        // Step 1: Pay August invoice only
        $response = $this->postJson('/api/resident/maintenance-payments', [
            'maintenance_bill_id' => $augustBill->id,
            'payment_method' => 'UPI',
        ]);

        $response->assertOk()
            ->assertJsonPath('data.amount', '2000.00')
            ->assertJsonPath('data.status', 'COMPLETED')
            ->assertJsonPath('data.maintenance_bill_id', $augustBill->id);

        // August is now PAID, September remains UNPAID
        $this->assertSame(MaintenanceBill::STATUS_PAID, $augustBill->fresh()->status);
        $this->assertSame(MaintenanceBill::STATUS_UNPAID, $septemberBill->fresh()->status);

        // Summary reflects remaining ₹2000 dues
        $summary = $this->getJson('/api/resident/payment-summary')->assertOk();
        $this->assertEquals(2000, $summary->json('data.total_due'));
        $this->assertEquals(1, $summary->json('data.bill_count'));

        // Step 2: Pay September invoice
        $response2 = $this->postJson('/api/resident/maintenance-payments', [
            'maintenance_bill_id' => $septemberBill->id,
            'payment_method' => 'UPI',
        ]);

        $response2->assertOk()
            ->assertJsonPath('data.amount', '2000.00')
            ->assertJsonPath('data.status', 'COMPLETED')
            ->assertJsonPath('data.maintenance_bill_id', $septemberBill->id);

        // Both are now PAID, total dues is 0
        $this->assertSame(MaintenanceBill::STATUS_PAID, $septemberBill->fresh()->status);
        $summaryAfter = $this->getJson('/api/resident/payment-summary')->assertOk();
        $this->assertEquals(0, $summaryAfter->json('data.total_due'));
        $this->assertEquals(0, $summaryAfter->json('data.bill_count'));
    }

    public function test_paying_already_paid_invoice_is_rejected(): void
    {
        config(['app.payment_mode' => 'demo']);

        $resident = $this->resident('602', 'demo-already-paid@test.local');
        $bill = MaintenanceBill::create([
            'flat_id' => $resident->flat_id,
            'billing_month' => '2026-08-01',
            'due_date' => '2026-08-15',
            'amount' => 2000,
            'status' => MaintenanceBill::STATUS_UNPAID,
        ]);

        Sanctum::actingAs($resident->user);

        // First payment succeeds
        $this->postJson('/api/resident/maintenance-payments', [
            'maintenance_bill_id' => $bill->id,
            'payment_method' => 'UPI',
        ])->assertOk();

        // Second payment attempt is rejected with 422
        $this->postJson('/api/resident/maintenance-payments', [
            'maintenance_bill_id' => $bill->id,
            'payment_method' => 'UPI',
        ])->assertUnprocessable()
            ->assertJsonValidationErrors('maintenance_bill_id');

        // Only 1 payment record exists in the database
        $this->assertDatabaseCount('maintenance_payments', 1);
    }

    public function test_resident_cannot_pay_another_residents_invoice(): void
    {
        config(['app.payment_mode' => 'demo']);

        $residentA = $this->resident('603', 'res-a@test.local');
        $residentB = $this->resident('604', 'res-b@test.local');

        $billB = MaintenanceBill::create([
            'flat_id' => $residentB->flat_id,
            'billing_month' => '2026-08-01',
            'due_date' => '2026-08-15',
            'amount' => 2000,
            'status' => MaintenanceBill::STATUS_UNPAID,
        ]);

        Sanctum::actingAs($residentA->user);

        $this->postJson('/api/resident/maintenance-payments', [
            'maintenance_bill_id' => $billB->id,
            'payment_method' => 'UPI',
        ])->assertForbidden();

        $this->assertDatabaseCount('maintenance_payments', 0);
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
