<?php

namespace Tests\Feature\Api;

use App\Models\Flat;
use App\Models\Resident;
use App\Models\User;
use App\Models\Visitor;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class VisitorManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_security_can_create_a_visitor_request_and_resident_is_notified(): void
    {
        $security = $this->user(User::ROLE_SECURITY, 'security@example.test');
        $resident = $this->resident('101', 'resident@example.test');
        Sanctum::actingAs($security);

        $response = $this->postJson('/api/security/visitors', $this->visitorPayload($resident));

        $response->assertCreated()
            ->assertJsonPath('data.approval_status', Visitor::APPROVAL_PENDING)
            ->assertJsonPath('data.entry_status', Visitor::ENTRY_WAITING)
            ->assertJsonPath('data.flat.id', $resident->flat_id);

        $this->assertDatabaseHas('visitors', [
            'resident_id' => $resident->id,
            'flat_id' => $resident->flat_id,
            'approval_status' => Visitor::APPROVAL_PENDING,
        ]);
        $this->assertDatabaseHas('notifications', [
            'notifiable_id' => $resident->user_id,
            'notifiable_type' => User::class,
        ]);
    }

    public function test_resident_only_sees_visitor_requests_for_their_own_flat(): void
    {
        $resident = $this->resident('101', 'resident@example.test');
        $otherResident = $this->resident('102', 'other@example.test');
        $ownVisitor = $this->visitor($resident);
        $this->visitor($otherResident);
        Sanctum::actingAs($resident->user);

        $this->getJson('/api/resident/visitors/pending')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $ownVisitor->id);
    }

    public function test_resident_can_approve_their_pending_visitor(): void
    {
        $resident = $this->resident('101', 'resident@example.test');
        $visitor = $this->visitor($resident);
        Sanctum::actingAs($resident->user);

        $this->patchJson("/api/resident/visitors/{$visitor->id}/approve")
            ->assertOk()
            ->assertJsonPath('data.approval_status', Visitor::APPROVAL_APPROVED);

        $this->assertDatabaseHas('visitors', [
            'id' => $visitor->id,
            'approval_status' => Visitor::APPROVAL_APPROVED,
            'approved_by_resident_id' => $resident->id,
        ]);
    }

    public function test_resident_can_reject_their_pending_visitor(): void
    {
        $resident = $this->resident('101', 'resident@example.test');
        $visitor = $this->visitor($resident);
        Sanctum::actingAs($resident->user);

        $this->patchJson("/api/resident/visitors/{$visitor->id}/reject", ['reason' => 'Not expected today'])
            ->assertOk()
            ->assertJsonPath('data.approval_status', Visitor::APPROVAL_REJECTED);

        $this->assertDatabaseHas('visitors', [
            'id' => $visitor->id,
            'approval_status' => Visitor::APPROVAL_REJECTED,
            'approval_note' => 'Not expected today',
        ]);
    }

    public function test_resident_cannot_access_another_residents_visitor(): void
    {
        $resident = $this->resident('101', 'resident@example.test');
        $otherResident = $this->resident('102', 'other@example.test');
        $visitor = $this->visitor($otherResident);
        Sanctum::actingAs($resident->user);

        $this->getJson("/api/resident/visitors/{$visitor->id}")->assertForbidden();
        $this->patchJson("/api/resident/visitors/{$visitor->id}/approve")->assertForbidden();
    }

    public function test_security_cannot_approve_a_visitor(): void
    {
        $security = $this->user(User::ROLE_SECURITY, 'security@example.test');
        $visitor = $this->visitor($this->resident('101', 'resident@example.test'));
        Sanctum::actingAs($security);

        $this->patchJson("/api/resident/visitors/{$visitor->id}/approve")->assertForbidden();
    }

    public function test_admin_can_view_all_visitors(): void
    {
        $admin = $this->user(User::ROLE_ADMIN, 'admin@example.test');
        $first = $this->visitor($this->resident('101', 'resident@example.test'));
        $second = $this->visitor($this->resident('102', 'other@example.test'));
        Sanctum::actingAs($admin);

        $this->getJson('/api/admin/visitors?q=Guest')
            ->assertOk()
            ->assertJsonCount(2, 'data')
            ->assertJsonFragment(['id' => $first->id])
            ->assertJsonFragment(['id' => $second->id]);
    }

    public function test_security_can_record_entry_and_exit_for_approved_visitor(): void
    {
        $security = $this->user(User::ROLE_SECURITY, 'security@example.test');
        $visitor = $this->visitor($this->resident('101', 'resident@example.test'), [
            'approval_status' => Visitor::APPROVAL_APPROVED,
            'approved_at' => now(),
        ]);
        Sanctum::actingAs($security);

        $this->patchJson("/api/security/visitors/{$visitor->id}/entry")
            ->assertOk()
            ->assertJsonPath('data.entry_status', Visitor::ENTRY_ENTERED);

        $this->patchJson("/api/security/visitors/{$visitor->id}/exit")
            ->assertOk()
            ->assertJsonPath('data.entry_status', Visitor::ENTRY_EXITED)
            ->assertJsonPath('data.approval_status', Visitor::APPROVAL_COMPLETED);
    }

    public function test_resident_can_preapprove_a_future_visitor(): void
    {
        $resident = $this->resident('101', 'resident@example.test');
        Sanctum::actingAs($resident->user);

        $this->postJson('/api/resident/visitors/pre-approvals', [
            'visitor_name' => 'Future Guest',
            'mobile_number' => '9999999999',
            'purpose' => 'Family visit',
            'visitor_type' => Visitor::TYPE_GUEST,
            'expected_at' => now()->addDay()->toISOString(),
        ])->assertCreated()
            ->assertJsonPath('data.is_pre_approved', true)
            ->assertJsonPath('data.approval_status', Visitor::APPROVAL_APPROVED);
    }

    public function test_invalid_visitor_data_and_unauthenticated_requests_are_rejected(): void
    {
        $this->postJson('/api/security/visitors', [])->assertUnauthorized();

        $security = $this->user(User::ROLE_SECURITY, 'security@example.test');
        Sanctum::actingAs($security);

        $this->postJson('/api/security/visitors', [
            'visitor_name' => '',
            'mobile_number' => '',
        ])->assertUnprocessable()
            ->assertJsonValidationErrors(['visitor_name', 'mobile_number', 'purpose', 'flat_id', 'resident_id', 'visitor_type']);
    }

    private function user(string $role, string $email): User
    {
        return User::create([
            'name' => $role.' User',
            'email' => $email,
            'password' => 'Password123!',
            'role' => $role,
        ]);
    }

    private function resident(string $flatNumber, string $email): Resident
    {
        $user = $this->user(User::ROLE_RESIDENT, $email);
        $flat = Flat::create([
            'flat_number' => $flatNumber,
            'building' => 'A',
            'occupancy_status' => 'OCCUPIED',
        ]);

        return Resident::create([
            'user_id' => $user->id,
            'flat_id' => $flat->id,
            'is_primary_contact' => true,
        ])->load('user');
    }

    private function visitor(Resident $resident, array $overrides = []): Visitor
    {
        return Visitor::create([
            'visitor_name' => 'Guest Visitor',
            'mobile_number' => '9999999999',
            'purpose' => 'Guest visit',
            'flat_id' => $resident->flat_id,
            'resident_id' => $resident->id,
            'visitor_type' => Visitor::TYPE_GUEST,
            'entry_status' => Visitor::ENTRY_WAITING,
            'approval_status' => Visitor::APPROVAL_PENDING,
            ...$overrides,
        ]);
    }

    private function visitorPayload(Resident $resident): array
    {
        return [
            'visitor_name' => 'Gate Guest',
            'mobile_number' => '9999999999',
            'purpose' => 'Meeting resident',
            'flat_id' => $resident->flat_id,
            'resident_id' => $resident->id,
            'visitor_type' => Visitor::TYPE_GUEST,
        ];
    }
}
