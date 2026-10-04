<?php

namespace Tests\Feature\Api;

use App\Models\Flat;
use App\Models\Resident;
use App\Models\User;
use App\Models\UserDeviceToken;
use App\Models\Visitor;
use App\Services\Notifications\FirebaseCloudMessagingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Laravel\Sanctum\Sanctum;
use Mockery;
use Tests\TestCase;

/**
 * Tests that FCM push notifications are sent for each visitor lifecycle event,
 * and that FCM failures never break the underlying business workflow.
 *
 * Firebase HTTP calls are always mocked — no real network calls.
 */
class VisitorFcmNotificationTest extends TestCase
{
    use RefreshDatabase;

    private User $securityUser;
    private User $residentUser;
    private Flat $flat;
    private Resident $resident;

    protected function setUp(): void
    {
        parent::setUp();
        Mail::fake();

        $this->securityUser = User::create([
            'name'     => 'Security Guard',
            'email'    => 'security@fcm.test',
            'password' => 'Password123!',
            'role'     => User::ROLE_SECURITY,
        ]);

        $this->residentUser = User::create([
            'name'     => 'Resident User',
            'email'    => 'resident@fcm.test',
            'password' => 'Password123!',
            'role'     => User::ROLE_RESIDENT,
        ]);

        $this->flat = Flat::create([
            'flat_number'      => '201',
            'building'         => 'B Wing',
            'occupancy_status' => 'OCCUPIED',
        ]);

        $this->resident = Resident::create([
            'user_id'            => $this->residentUser->id,
            'flat_id'            => $this->flat->id,
            'is_primary_contact' => true,
        ]);
    }

    // ── Helpers ──────────────────────────────────────────────────────────────

    private function createVisitorViaApi(): Visitor
    {
        Sanctum::actingAs($this->securityUser);
        $response = $this->postJson('/api/security/visitors', [
            'visitor_name' => 'Test Visitor',
            'flat_id'      => $this->flat->id,
            'resident_id'  => $this->resident->id,
            'visitor_type' => 'GUEST',
        ]);
        $response->assertCreated();
        return Visitor::find($response->json('data.id'));
    }

    private function registerToken(User $user): UserDeviceToken
    {
        return UserDeviceToken::create([
            'user_id'      => $user->id,
            'fcm_token'    => 'fake_fcm_token_' . $user->id,
            'platform'     => 'android',
            'is_active'    => true,
            'last_used_at' => now(),
        ]);
    }

    private function mockFcmSuccess(): void
    {
        $mock = Mockery::mock(FirebaseCloudMessagingService::class);
        $mock->shouldReceive('sendToUser')
            ->andReturn(['status' => 'PROCESSED', 'delivered' => 1]);
        $mock->shouldReceive('sendVisitorApprovalNotification')
            ->andReturn(['status' => 'PROCESSED', 'sent' => 1]);
        $this->app->instance(FirebaseCloudMessagingService::class, $mock);
    }

    private function mockFcmFailure(): void
    {
        $mock = Mockery::mock(FirebaseCloudMessagingService::class);
        $mock->shouldReceive('sendToUser')
            ->andThrow(new \RuntimeException('Simulated FCM failure'));
        $mock->shouldReceive('sendVisitorApprovalNotification')
            ->andThrow(new \RuntimeException('Simulated FCM failure'));
        $this->app->instance(FirebaseCloudMessagingService::class, $mock);
    }

    // ── Visitor APPROVE ───────────────────────────────────────────────────────

    public function test_visitor_approve_creates_db_notification_for_security(): void
    {
        $this->registerToken($this->securityUser);
        $this->mockFcmSuccess();

        $visitor = $this->createVisitorViaApi();

        Sanctum::actingAs($this->residentUser);
        $this->patchJson("/api/resident/visitors/{$visitor->id}/approve")
            ->assertOk()
            ->assertJsonPath('data.approval_status', 'APPROVED');

        $notification = $this->securityUser->notifications()->latest()->first();
        $this->assertNotNull($notification, 'Expected a DB notification for the security guard');
        $this->assertEquals('visitor_approved', $notification->data['type']);
        $this->assertEquals($visitor->id, $notification->data['visitor_id']);
    }

    public function test_visitor_approve_fcm_failure_does_not_break_workflow(): void
    {
        $this->mockFcmFailure();
        $visitor = $this->createVisitorViaApi();

        Sanctum::actingAs($this->residentUser);
        $this->patchJson("/api/resident/visitors/{$visitor->id}/approve")
            ->assertOk()
            ->assertJsonPath('data.approval_status', 'APPROVED');

        $this->assertDatabaseHas('visitors', ['id' => $visitor->id, 'approval_status' => 'APPROVED']);
    }

    // ── Visitor REJECT ────────────────────────────────────────────────────────

    public function test_visitor_reject_creates_db_notification_for_security(): void
    {
        $this->registerToken($this->securityUser);
        $this->mockFcmSuccess();

        $visitor = $this->createVisitorViaApi();

        Sanctum::actingAs($this->residentUser);
        $this->patchJson("/api/resident/visitors/{$visitor->id}/reject", ['reason' => 'Not expected'])
            ->assertOk()
            ->assertJsonPath('data.approval_status', 'REJECTED');

        $notification = $this->securityUser->notifications()->latest()->first();
        $this->assertNotNull($notification, 'Expected a DB notification for the security guard');
        $this->assertEquals('visitor_rejected', $notification->data['type']);
    }

    public function test_visitor_reject_fcm_failure_does_not_break_workflow(): void
    {
        $this->mockFcmFailure();
        $visitor = $this->createVisitorViaApi();

        Sanctum::actingAs($this->residentUser);
        $this->patchJson("/api/resident/visitors/{$visitor->id}/reject")
            ->assertOk()
            ->assertJsonPath('data.approval_status', 'REJECTED');

        $this->assertDatabaseHas('visitors', ['id' => $visitor->id, 'approval_status' => 'REJECTED']);
    }

    // ── Visitor CHECK-IN ──────────────────────────────────────────────────────

    public function test_visitor_check_in_creates_db_notification_for_resident(): void
    {
        $this->registerToken($this->residentUser);
        $this->mockFcmSuccess();

        $visitor = $this->createVisitorViaApi();

        Sanctum::actingAs($this->residentUser);
        $this->patchJson("/api/resident/visitors/{$visitor->id}/approve")->assertOk();

        Sanctum::actingAs($this->securityUser);
        $this->patchJson("/api/security/visitors/{$visitor->id}/entry")
            ->assertOk()
            ->assertJsonPath('data.entry_status', 'ENTERED');

        $notification = $this->residentUser->notifications()
            ->get()
            ->first(fn ($n) => ($n->data['type'] ?? '') === 'visitor_checked_in');

        $this->assertNotNull($notification, 'Expected a visitor_checked_in notification for the resident');
        $this->assertEquals($visitor->id, $notification->data['visitor_id']);
    }

    public function test_visitor_check_in_fcm_failure_does_not_break_workflow(): void
    {
        $this->mockFcmFailure();
        $visitor = $this->createVisitorViaApi();

        Sanctum::actingAs($this->residentUser);
        $this->patchJson("/api/resident/visitors/{$visitor->id}/approve")->assertOk();

        Sanctum::actingAs($this->securityUser);
        $this->patchJson("/api/security/visitors/{$visitor->id}/entry")
            ->assertOk()
            ->assertJsonPath('data.entry_status', 'ENTERED');

        $this->assertDatabaseHas('visitors', ['id' => $visitor->id, 'entry_status' => 'ENTERED']);
    }

    // ── Visitor CHECK-OUT ─────────────────────────────────────────────────────

    public function test_visitor_check_out_creates_db_notification_for_resident(): void
    {
        $this->registerToken($this->residentUser);
        $this->mockFcmSuccess();

        $visitor = $this->createVisitorViaApi();

        Sanctum::actingAs($this->residentUser);
        $this->patchJson("/api/resident/visitors/{$visitor->id}/approve")->assertOk();

        Sanctum::actingAs($this->securityUser);
        $this->patchJson("/api/security/visitors/{$visitor->id}/entry")->assertOk();
        $this->patchJson("/api/security/visitors/{$visitor->id}/exit")
            ->assertOk()
            ->assertJsonPath('data.entry_status', 'EXITED');

        $notification = $this->residentUser->notifications()
            ->get()
            ->first(fn ($n) => ($n->data['type'] ?? '') === 'visitor_checked_out');

        $this->assertNotNull($notification, 'Expected a visitor_checked_out notification for the resident');
        $this->assertEquals($visitor->id, $notification->data['visitor_id']);
    }

    public function test_visitor_check_out_fcm_failure_does_not_break_workflow(): void
    {
        $this->mockFcmFailure();
        $visitor = $this->createVisitorViaApi();

        Sanctum::actingAs($this->residentUser);
        $this->patchJson("/api/resident/visitors/{$visitor->id}/approve")->assertOk();

        Sanctum::actingAs($this->securityUser);
        $this->patchJson("/api/security/visitors/{$visitor->id}/entry")->assertOk();
        $this->patchJson("/api/security/visitors/{$visitor->id}/exit")
            ->assertOk()
            ->assertJsonPath('data.entry_status', 'EXITED');

        $this->assertDatabaseHas('visitors', ['id' => $visitor->id, 'entry_status' => 'EXITED']);
    }

    // ── Authorization ─────────────────────────────────────────────────────────

    public function test_unauthenticated_user_cannot_access_notifications(): void
    {
        $this->getJson('/api/notifications')->assertUnauthorized();
        $this->getJson('/api/notifications/unread-count')->assertUnauthorized();
        $this->patchJson('/api/notifications/read-all')->assertUnauthorized();
    }

    public function test_device_token_requires_authentication(): void
    {
        $this->postJson('/api/device-tokens', [
            'fcm_token' => 'some_token',
            'platform'  => 'android',
        ])->assertUnauthorized();
    }

    public function test_device_token_registered_for_authenticated_user_only(): void
    {
        Sanctum::actingAs($this->residentUser);
        $this->postJson('/api/device-tokens', [
            'fcm_token' => 'valid_token_xyz',
            'platform'  => 'android',
        ])->assertStatus(201);

        $this->assertDatabaseHas('user_device_tokens', [
            'user_id'   => $this->residentUser->id,
            'fcm_token' => 'valid_token_xyz',
        ]);
    }

    public function test_duplicate_device_token_not_created(): void
    {
        Sanctum::actingAs($this->residentUser);

        $this->postJson('/api/device-tokens', ['fcm_token' => 'dup_token', 'platform' => 'android'])
            ->assertStatus(201);
        $this->postJson('/api/device-tokens', ['fcm_token' => 'dup_token', 'platform' => 'android'])
            ->assertStatus(201);

        $this->assertEquals(
            1,
            UserDeviceToken::where('user_id', $this->residentUser->id)
                ->where('fcm_token', 'dup_token')
                ->count()
        );
    }
}
