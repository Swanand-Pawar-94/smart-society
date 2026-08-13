<?php

namespace Tests\Feature\Api;

use App\Models\Flat;
use App\Models\Resident;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ComplaintNoticeTest extends TestCase
{
    use RefreshDatabase;

    public function test_resident_can_create_a_complaint_and_another_resident_cannot_view_it(): void
    {
        [$residentUser, $resident] = $this->resident();
        [, $otherResident] = $this->resident();

        $created = $this->actingAs($residentUser, 'sanctum')->postJson('/api/resident/complaints', [
            'category' => 'Plumbing', 'title' => 'Leak', 'description' => 'A tap is leaking.', 'priority' => 'HIGH',
        ]);
        $created->assertOk()->assertJsonPath('data.status', 'OPEN');

        $this->actingAs($otherResident->user, 'sanctum')->getJson('/api/resident/complaints/'.$created->json('data.id'))->assertForbidden();
    }

    public function test_admin_can_publish_a_notice_but_resident_cannot_see_a_draft(): void
    {
        $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);
        [$residentUser] = $this->resident();
        $draft = $this->actingAs($admin, 'sanctum')->postJson('/api/admin/notices', ['title' => 'Draft', 'content' => 'Not yet public', 'audience' => 'RESIDENTS']);
        $draft->assertCreated();

        $this->actingAs($residentUser, 'sanctum')->getJson('/api/resident/notices/'.$draft->json('data.id'))->assertForbidden();
        $this->actingAs($admin, 'sanctum')->patchJson('/api/admin/notices/'.$draft->json('data.id'), ['is_published' => true])->assertOk();
        $this->actingAs($residentUser, 'sanctum')->getJson('/api/resident/notices/'.$draft->json('data.id'))->assertOk()->assertJsonPath('data.is_published', true);
    }

    private function resident(): array
    {
        $user = User::factory()->create(['role' => User::ROLE_RESIDENT]);
        $flat = Flat::create(['flat_number' => 'F'.fake()->unique()->numberBetween(100, 999), 'building' => 'A', 'floor' => 1, 'occupancy_status' => 'OCCUPIED']);
        $resident = Resident::create(['user_id' => $user->id, 'flat_id' => $flat->id, 'relation_to_owner' => 'OWNER', 'is_primary_contact' => true]);

        return [$user, $resident];
    }
}
