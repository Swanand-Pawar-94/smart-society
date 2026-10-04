<?php

namespace Tests\Feature\Api;

use App\Models\Flat;
use App\Models\Resident;
use App\Models\User;
use App\Models\Visitor;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class SecurityVisitorWorkflowTest extends TestCase
{
    use RefreshDatabase;

    private User $security;
    private Resident $resident;
    private Flat $flat;

    protected function setUp(): void
    {
        parent::setUp();

        $this->security = User::create([
            'name' => 'Security Guard',
            'email' => 'security@example.test',
            'password' => 'Password123!',
            'role' => User::ROLE_SECURITY,
        ]);

        $residentUser = User::create([
            'name' => 'Resident User',
            'email' => 'resident@example.test',
            'password' => 'Password123!',
            'role' => User::ROLE_RESIDENT,
        ]);

        $this->flat = Flat::create([
            'flat_number' => '101',
            'building' => 'Tower A',
            'floor' => '1',
            'occupancy_status' => 'OCCUPIED',
        ]);

        $this->resident = Resident::create([
            'user_id' => $residentUser->id,
            'flat_id' => $this->flat->id,
            'is_primary_contact' => true,
        ]);
    }

    public function test_eligible_for_check_in_filter_returns_only_eligible_visitors(): void
    {
        Sanctum::actingAs($this->security);

        // 1. Eligible pre-approved visitor
        $eligibleExpected = Visitor::create([
            'visitor_name' => 'Eligible Expected',
            'mobile_number' => '9999900001',
            'purpose' => 'Visit',
            'flat_id' => $this->flat->id,
            'resident_id' => $this->resident->id,
            'visitor_type' => Visitor::TYPE_GUEST,
            'entry_status' => Visitor::ENTRY_EXPECTED,
            'approval_status' => Visitor::APPROVAL_APPROVED,
            'approved_at' => now(),
            'is_pre_approved' => true,
        ]);

        // 2. Eligible gate visitor approved by resident
        $eligibleWaiting = Visitor::create([
            'visitor_name' => 'Eligible Waiting',
            'mobile_number' => '9999900002',
            'purpose' => 'Delivery',
            'flat_id' => $this->flat->id,
            'resident_id' => $this->resident->id,
            'visitor_type' => Visitor::TYPE_DELIVERY,
            'entry_status' => Visitor::ENTRY_WAITING,
            'approval_status' => Visitor::APPROVAL_APPROVED,
            'approved_at' => now(),
        ]);

        // 3. Pending visitor (not eligible)
        $pending = Visitor::create([
            'visitor_name' => 'Pending Visitor',
            'mobile_number' => '9999900003',
            'purpose' => 'Cab',
            'flat_id' => $this->flat->id,
            'resident_id' => $this->resident->id,
            'visitor_type' => Visitor::TYPE_CAB,
            'entry_status' => Visitor::ENTRY_WAITING,
            'approval_status' => Visitor::APPROVAL_PENDING,
        ]);

        // 4. Rejected visitor (not eligible)
        $rejected = Visitor::create([
            'visitor_name' => 'Rejected Visitor',
            'mobile_number' => '9999900004',
            'purpose' => 'Visit',
            'flat_id' => $this->flat->id,
            'resident_id' => $this->resident->id,
            'visitor_type' => Visitor::TYPE_GUEST,
            'entry_status' => Visitor::ENTRY_WAITING,
            'approval_status' => Visitor::APPROVAL_REJECTED,
        ]);

        // 5. Already entered visitor (not eligible)
        $entered = Visitor::create([
            'visitor_name' => 'Entered Visitor',
            'mobile_number' => '9999900005',
            'purpose' => 'Guest',
            'flat_id' => $this->flat->id,
            'resident_id' => $this->resident->id,
            'visitor_type' => Visitor::TYPE_GUEST,
            'entry_status' => Visitor::ENTRY_ENTERED,
            'approval_status' => Visitor::APPROVAL_APPROVED,
            'entered_at' => now(),
        ]);

        // 6. Already exited visitor (not eligible)
        $exited = Visitor::create([
            'visitor_name' => 'Exited Visitor',
            'mobile_number' => '9999900006',
            'purpose' => 'Guest',
            'flat_id' => $this->flat->id,
            'resident_id' => $this->resident->id,
            'visitor_type' => Visitor::TYPE_GUEST,
            'entry_status' => Visitor::ENTRY_EXITED,
            'approval_status' => Visitor::APPROVAL_COMPLETED,
            'entered_at' => now()->subHour(),
            'exited_at' => now(),
        ]);

        $response = $this->getJson('/api/security/visitors?eligible_for_check_in=true');

        $response->assertOk()
            ->assertJsonCount(2, 'data')
            ->assertJsonFragment(['id' => $eligibleExpected->id])
            ->assertJsonFragment(['id' => $eligibleWaiting->id])
            ->assertJsonMissing(['id' => $pending->id])
            ->assertJsonMissing(['id' => $rejected->id])
            ->assertJsonMissing(['id' => $entered->id])
            ->assertJsonMissing(['id' => $exited->id]);
    }

    public function test_entry_status_entered_returns_only_currently_inside_visitors(): void
    {
        Sanctum::actingAs($this->security);

        $inside = Visitor::create([
            'visitor_name' => 'Currently Inside',
            'mobile_number' => '9999900010',
            'purpose' => 'Meeting',
            'flat_id' => $this->flat->id,
            'resident_id' => $this->resident->id,
            'visitor_type' => Visitor::TYPE_GUEST,
            'entry_status' => Visitor::ENTRY_ENTERED,
            'approval_status' => Visitor::APPROVAL_APPROVED,
            'entered_at' => now(),
        ]);

        $waiting = Visitor::create([
            'visitor_name' => 'Waiting Outside',
            'mobile_number' => '9999900011',
            'purpose' => 'Waiting',
            'flat_id' => $this->flat->id,
            'resident_id' => $this->resident->id,
            'visitor_type' => Visitor::TYPE_GUEST,
            'entry_status' => Visitor::ENTRY_WAITING,
            'approval_status' => Visitor::APPROVAL_APPROVED,
        ]);

        $exited = Visitor::create([
            'visitor_name' => 'Already Exited',
            'mobile_number' => '9999900012',
            'purpose' => 'Finished',
            'flat_id' => $this->flat->id,
            'resident_id' => $this->resident->id,
            'visitor_type' => Visitor::TYPE_GUEST,
            'entry_status' => Visitor::ENTRY_EXITED,
            'approval_status' => Visitor::APPROVAL_COMPLETED,
            'entered_at' => now()->subHours(2),
            'exited_at' => now()->subHour(),
        ]);

        $response = $this->getJson('/api/security/visitors?entry_status=ENTERED');

        $response->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $inside->id)
            ->assertJsonMissing(['id' => $waiting->id])
            ->assertJsonMissing(['id' => $exited->id]);
    }

    public function test_dashboard_counters_update_accurately_across_check_in_and_checkout(): void
    {
        Sanctum::actingAs($this->security);

        // Initially 0 checked in, 0 checked out
        $this->getJson('/api/security/dashboard')
            ->assertOk()
            ->assertJsonPath('data.checked_in_today', 0)
            ->assertJsonPath('data.checked_out_today', 0);

        // Create an approved visitor
        $visitor = Visitor::create([
            'visitor_name' => 'Workflow Visitor',
            'mobile_number' => '9999900020',
            'purpose' => 'Testing workflow',
            'flat_id' => $this->flat->id,
            'resident_id' => $this->resident->id,
            'visitor_type' => Visitor::TYPE_GUEST,
            'entry_status' => Visitor::ENTRY_WAITING,
            'approval_status' => Visitor::APPROVAL_APPROVED,
            'approved_at' => now(),
        ]);

        // Security records entry
        $this->patchJson("/api/security/visitors/{$visitor->id}/entry")->assertOk();

        // Dashboard now shows 1 checked in, 0 checked out
        $this->getJson('/api/security/dashboard')
            ->assertOk()
            ->assertJsonPath('data.checked_in_today', 1)
            ->assertJsonPath('data.checked_out_today', 0);

        // Security records exit
        $this->patchJson("/api/security/visitors/{$visitor->id}/exit")->assertOk();

        // Dashboard now shows 0 checked in (no longer inside), 1 checked out today
        $this->getJson('/api/security/dashboard')
            ->assertOk()
            ->assertJsonPath('data.checked_in_today', 0)
            ->assertJsonPath('data.checked_out_today', 1);
    }

    public function test_double_check_in_protection_fails_safely_on_second_request(): void
    {
        Sanctum::actingAs($this->security);

        $visitor = Visitor::create([
            'visitor_name' => 'Double Check In Visitor',
            'mobile_number' => '9999900030',
            'purpose' => 'Race test',
            'flat_id' => $this->flat->id,
            'resident_id' => $this->resident->id,
            'visitor_type' => Visitor::TYPE_GUEST,
            'entry_status' => Visitor::ENTRY_WAITING,
            'approval_status' => Visitor::APPROVAL_APPROVED,
            'approved_at' => now(),
        ]);

        // First check in succeeds
        $first = $this->patchJson("/api/security/visitors/{$visitor->id}/entry");
        $first->assertOk()->assertJsonPath('data.entry_status', Visitor::ENTRY_ENTERED);

        $firstTimestamp = $visitor->fresh()->entered_at;
        $this->assertNotNull($firstTimestamp);

        // Second check in must safely fail
        $second = $this->patchJson("/api/security/visitors/{$visitor->id}/entry");
        $second->assertUnprocessable();

        // Timestamp must remain exactly the first timestamp
        $this->assertEquals($firstTimestamp->toIso8601String(), $visitor->fresh()->entered_at->toIso8601String());
    }

    public function test_double_checkout_protection_fails_safely_on_second_request(): void
    {
        Sanctum::actingAs($this->security);

        $visitor = Visitor::create([
            'visitor_name' => 'Double Check Out Visitor',
            'mobile_number' => '9999900040',
            'purpose' => 'Race test',
            'flat_id' => $this->flat->id,
            'resident_id' => $this->resident->id,
            'visitor_type' => Visitor::TYPE_GUEST,
            'entry_status' => Visitor::ENTRY_ENTERED,
            'approval_status' => Visitor::APPROVAL_APPROVED,
            'entered_at' => now()->subHour(),
        ]);

        // First exit succeeds
        $first = $this->patchJson("/api/security/visitors/{$visitor->id}/exit");
        $first->assertOk()->assertJsonPath('data.entry_status', Visitor::ENTRY_EXITED);

        $firstExitTimestamp = $visitor->fresh()->exited_at;
        $this->assertNotNull($firstExitTimestamp);

        // Second exit must safely fail
        $second = $this->patchJson("/api/security/visitors/{$visitor->id}/exit");
        $second->assertUnprocessable();

        // Exit timestamp must not be overwritten
        $this->assertEquals($firstExitTimestamp->toIso8601String(), $visitor->fresh()->exited_at->toIso8601String());
    }

    public function test_unauthenticated_and_resident_users_are_forbidden_from_security_operations(): void
    {
        $visitor = Visitor::create([
            'visitor_name' => 'Auth Test Visitor',
            'mobile_number' => '9999900050',
            'purpose' => 'Auth test',
            'flat_id' => $this->flat->id,
            'resident_id' => $this->resident->id,
            'visitor_type' => Visitor::TYPE_GUEST,
            'entry_status' => Visitor::ENTRY_WAITING,
            'approval_status' => Visitor::APPROVAL_APPROVED,
        ]);

        // Unauthenticated
        $this->getJson('/api/security/visitors')->assertUnauthorized();
        $this->patchJson("/api/security/visitors/{$visitor->id}/entry")->assertUnauthorized();
        $this->patchJson("/api/security/visitors/{$visitor->id}/exit")->assertUnauthorized();

        // Resident user
        Sanctum::actingAs($this->resident->user);
        $this->getJson('/api/security/visitors')->assertForbidden();
        $this->patchJson("/api/security/visitors/{$visitor->id}/entry")->assertForbidden();
        $this->patchJson("/api/security/visitors/{$visitor->id}/exit")->assertForbidden();
    }
}
