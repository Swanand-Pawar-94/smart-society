<?php

namespace Tests\Feature\Api;

use App\Models\Flat;
use App\Models\Resident;
use App\Models\User;
use App\Models\Visitor;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class VisitorNotificationEndToEndTest extends TestCase
{
    use RefreshDatabase;

    public function test_exact_security_to_same_device_resident_notification_and_approval_flow(): void
    {
        Mail::fake();

        // 1. Create Security User, Resident User, Flat, and Resident Association
        $securityUser = User::create([
            'name' => 'Security Guard',
            'email' => 'security@society.test',
            'password' => bcrypt('Security123!'),
            'role' => User::ROLE_SECURITY,
        ]);

        $residentUser = User::create([
            'name' => 'John Resident',
            'email' => 'resident@society.test',
            'password' => bcrypt('Resident123!'),
            'role' => User::ROLE_RESIDENT,
        ]);

        $flat = Flat::create([
            'flat_number' => '402',
            'building' => 'H wing',
            'occupancy_status' => 'OCCUPIED',
        ]);

        $resident = Resident::create([
            'user_id' => $residentUser->id,
            'flat_id' => $flat->id,
            'is_primary_contact' => true,
        ]);

        // ==========================================
        // STEP 1: SECURITY CREATES VISITOR REQUEST
        // ==========================================
        Sanctum::actingAs($securityUser);

        $createResponse = $this->postJson('/api/security/visitors', [
            'visitor_name' => 'rishi',
            'flat_id' => $flat->id,
            'resident_id' => $resident->id,
            'visitor_type' => 'GUEST',
        ]);

        $createResponse->assertCreated()
            ->assertJsonPath('data.visitor_name', 'rishi')
            ->assertJsonPath('data.approval_status', 'PENDING')
            ->assertJsonPath('data.entry_status', 'WAITING');

        $visitorId = $createResponse->json('data.id');

        // ==========================================
        // STEP 2: VERIFY MYSQL VISITOR & NOTIFICATION
        // ==========================================
        $this->assertDatabaseHas('visitors', [
            'id' => $visitorId,
            'visitor_name' => 'rishi',
            'flat_id' => $flat->id,
            'resident_id' => $resident->id,
            'approval_status' => 'PENDING',
            'entry_status' => 'WAITING',
        ]);

        $this->assertDatabaseHas('notifications', [
            'notifiable_type' => User::class,
            'notifiable_id' => $residentUser->id,
            'read_at' => null,
        ]);

        $dbNotification = $residentUser->notifications()->latest()->first();
        $this->assertNotNull($dbNotification);
        $this->assertEquals('visitor_request', $dbNotification->data['type']);
        $this->assertEquals('Visitor Request', $dbNotification->data['title']);
        $this->assertStringContainsString('rishi is requesting entry to Flat 402, H wing.', $dbNotification->data['message']);
        $this->assertEquals($visitorId, $dbNotification->data['visitor_id']);

        // ==========================================
        // STEP 3: SECURITY LOGS OUT (SAME DEVICE SIMULATION)
        // ==========================================
        $this->postJson('/api/auth/logout')->assertOk();

        // ==========================================
        // STEP 4: RESIDENT LOGS IN
        // ==========================================
        Sanctum::actingAs($residentUser);

        // ==========================================
        // STEP 5: RESIDENT FETCHES UNREAD COUNT
        // ==========================================
        $countResponse = $this->getJson('/api/notifications/unread-count');
        $countResponse->assertOk()
            ->assertJsonPath('count', 1)
            ->assertJsonPath('data.count', 1);

        // ==========================================
        // STEP 6: RESIDENT FETCHES NOTIFICATIONS LIST
        // ==========================================
        $notifListResponse = $this->getJson('/api/notifications');
        $notifListResponse->assertOk()
            ->assertJsonPath('data.total', 1)
            ->assertJsonPath('data.data.0.id', $dbNotification->id)
            ->assertJsonPath('data.data.0.data.type', 'visitor_request')
            ->assertJsonPath('data.data.0.data.title', 'Visitor Request')
            ->assertJsonPath('data.data.0.data.visitor_name', 'rishi')
            ->assertJsonPath('data.data.0.read_at', null);

        // ==========================================
        // STEP 7: RESIDENT MARKS NOTIFICATION AS READ
        // ==========================================
        $readResponse = $this->patchJson("/api/notifications/{$dbNotification->id}/read");
        $readResponse->assertOk()
            ->assertJsonPath('data.read_at', fn ($val) => !is_null($val));

        $countAfterRead = $this->getJson('/api/notifications/unread-count');
        $countAfterRead->assertOk()
            ->assertJsonPath('count', 0);

        // ==========================================
        // STEP 8: RESIDENT APPROVES VISITOR REQUEST
        // ==========================================
        $approveResponse = $this->patchJson("/api/resident/visitors/{$visitorId}/approve");
        $approveResponse->assertOk()
            ->assertJsonPath('data.id', $visitorId)
            ->assertJsonPath('data.approval_status', 'APPROVED');

        $this->assertDatabaseHas('visitors', [
            'id' => $visitorId,
            'approval_status' => 'APPROVED',
            'approved_by_resident_id' => $resident->id,
        ]);

        // ==========================================
        // STEP 9: SECURITY SEES UPDATED STATUS
        // ==========================================
        Sanctum::actingAs($securityUser);
        $securityView = $this->getJson("/api/security/visitors/{$visitorId}");
        $securityView->assertOk()
            ->assertJsonPath('data.approval_status', 'APPROVED');
    }

    public function test_resident_can_reject_visitor_from_notification_flow(): void
    {
        Mail::fake();

        $securityUser = User::create([
            'name' => 'Security Guard',
            'email' => 'security2@society.test',
            'password' => bcrypt('Security123!'),
            'role' => User::ROLE_SECURITY,
        ]);

        $residentUser = User::create([
            'name' => 'Jane Resident',
            'email' => 'resident2@society.test',
            'password' => bcrypt('Resident123!'),
            'role' => User::ROLE_RESIDENT,
        ]);

        $flat = Flat::create([
            'flat_number' => '102',
            'building' => 'A wing',
            'occupancy_status' => 'OCCUPIED',
        ]);

        $resident = Resident::create([
            'user_id' => $residentUser->id,
            'flat_id' => $flat->id,
            'is_primary_contact' => true,
        ]);

        Sanctum::actingAs($securityUser);
        $createResponse = $this->postJson('/api/security/visitors', [
            'visitor_name' => 'Delivery Guy',
            'flat_id' => $flat->id,
            'resident_id' => $resident->id,
            'visitor_type' => 'DELIVERY',
        ]);
        $visitorId = $createResponse->json('data.id');

        Sanctum::actingAs($residentUser);
        $this->patchJson("/api/resident/visitors/{$visitorId}/reject", [
            'reason' => 'Not expecting delivery',
        ])->assertOk()
            ->assertJsonPath('data.approval_status', 'REJECTED');

        $this->assertDatabaseHas('visitors', [
            'id' => $visitorId,
            'approval_status' => 'REJECTED',
            'approval_note' => 'Not expecting delivery',
        ]);
    }
}
