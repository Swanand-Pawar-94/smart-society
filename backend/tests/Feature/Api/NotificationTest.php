<?php

namespace Tests\Feature\Api;

use App\Models\Flat;
use App\Models\Resident;
use App\Models\User;
use App\Models\Visitor;
use App\Notifications\VisitorAwaitingApproval;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class NotificationTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_can_get_only_their_own_notifications(): void
    {
        $resident1 = $this->resident('101', 'resident1@example.test');
        $resident2 = $this->resident('102', 'resident2@example.test');
        $visitor = $this->visitor($resident1);

        // Notify user 1
        $resident1->user->notify(new VisitorAwaitingApproval($visitor));

        // As User 1
        Sanctum::actingAs($resident1->user);
        $response1 = $this->getJson('/api/notifications');
        $response1->assertOk()
            ->assertJsonPath('data.total', 1)
            ->assertJsonPath('data.data.0.data.type', 'visitor_request')
            ->assertJsonPath('data.data.0.data.title', 'Visitor Request')
            ->assertJsonPath('data.data.0.data.visitor_name', $visitor->visitor_name);

        // As User 2
        Sanctum::actingAs($resident2->user);
        $response2 = $this->getJson('/api/notifications');
        $response2->assertOk()
            ->assertJsonPath('data.total', 0);
    }

    public function test_user_can_get_unread_notification_count(): void
    {
        $resident = $this->resident('101', 'resident@example.test');
        $visitor = $this->visitor($resident);

        Sanctum::actingAs($resident->user);

        // Initial count is 0
        $this->getJson('/api/notifications/unread-count')
            ->assertOk()
            ->assertJsonPath('count', 0)
            ->assertJsonPath('data.count', 0);

        // Notify user
        $resident->user->notify(new VisitorAwaitingApproval($visitor));

        // Count should be 1
        $this->getJson('/api/notifications/unread-count')
            ->assertOk()
            ->assertJsonPath('count', 1)
            ->assertJsonPath('data.count', 1);
    }

    public function test_user_can_mark_notification_as_read(): void
    {
        $resident = $this->resident('101', 'resident@example.test');
        $visitor = $this->visitor($resident);

        $resident->user->notify(new VisitorAwaitingApproval($visitor));
        $notification = $resident->user->notifications()->first();

        Sanctum::actingAs($resident->user);

        $this->patchJson("/api/notifications/{$notification->id}/read")
            ->assertOk()
            ->assertJsonPath('data.read_at', fn ($val) => !is_null($val));

        $this->getJson('/api/notifications/unread-count')
            ->assertOk()
            ->assertJsonPath('count', 0);
    }

    public function test_user_cannot_mark_other_users_notification_as_read(): void
    {
        $resident1 = $this->resident('101', 'resident1@example.test');
        $resident2 = $this->resident('102', 'resident2@example.test');
        $visitor = $this->visitor($resident1);

        $resident1->user->notify(new VisitorAwaitingApproval($visitor));
        $notification = $resident1->user->notifications()->first();

        Sanctum::actingAs($resident2->user);

        $this->patchJson("/api/notifications/{$notification->id}/read")
            ->assertNotFound();
    }

    public function test_user_can_mark_all_notifications_as_read(): void
    {
        $resident = $this->resident('101', 'resident@example.test');
        $visitor1 = $this->visitor($resident);
        $visitor2 = $this->visitor($resident);

        $resident->user->notify(new VisitorAwaitingApproval($visitor1));
        $resident->user->notify(new VisitorAwaitingApproval($visitor2));

        Sanctum::actingAs($resident->user);

        $this->getJson('/api/notifications/unread-count')
            ->assertOk()
            ->assertJsonPath('count', 2);

        $this->patchJson('/api/notifications/read-all')
            ->assertOk();

        $this->getJson('/api/notifications/unread-count')
            ->assertOk()
            ->assertJsonPath('count', 0);
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

    private function visitor(Resident $resident): Visitor
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
        ]);
    }
}
