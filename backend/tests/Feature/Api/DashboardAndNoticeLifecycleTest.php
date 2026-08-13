<?php

namespace Tests\Feature\Api;

use App\Models\Notice;
use App\Models\StaffMember;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class DashboardAndNoticeLifecycleTest extends TestCase
{
    use RefreshDatabase;

    public function test_staff_and_security_only_receive_their_own_dashboards(): void
    {
        $staff = User::create([
            'name' => 'Maintenance Staff',
            'email' => 'staff-dashboard@test.local',
            'password' => 'Password123!',
            'role' => User::ROLE_STAFF,
        ]);
        StaffMember::create([
            'user_id' => $staff->id,
            'name' => $staff->name,
            'mobile' => '9876543210',
            'designation' => 'Maintenance Technician',
            'status' => StaffMember::STATUS_ACTIVE,
            'joining_date' => '2026-01-01',
        ]);
        Sanctum::actingAs($staff);
        $this->getJson('/api/staff/dashboard')
            ->assertOk()
            ->assertJsonPath('data.staff_member.designation', 'Maintenance Technician');
        $this->getJson('/api/security/dashboard')->assertForbidden();

        $security = User::create([
            'name' => 'Security Guard',
            'email' => 'security-dashboard@test.local',
            'password' => 'Password123!',
            'role' => User::ROLE_SECURITY,
        ]);
        Sanctum::actingAs($security);
        $this->getJson('/api/security/dashboard')
            ->assertOk()
            ->assertJsonPath('data.checked_in_today', 0);
    }

    public function test_expired_notice_is_hidden_from_residents_but_not_admins(): void
    {
        $admin = User::create([
            'name' => 'Admin',
            'email' => 'notice-admin@test.local',
            'password' => 'Password123!',
            'role' => User::ROLE_ADMIN,
        ]);
        $resident = User::create([
            'name' => 'Resident',
            'email' => 'notice-resident@test.local',
            'password' => 'Password123!',
            'role' => User::ROLE_RESIDENT,
        ]);
        $notice = Notice::create([
            'created_by_user_id' => $admin->id,
            'title' => 'Expired water shutdown',
            'content' => 'This is no longer current.',
            'audience' => Notice::AUDIENCE_RESIDENTS,
            'is_published' => true,
            'published_at' => now()->subDays(3),
            'expires_at' => now()->subDay(),
        ]);

        Sanctum::actingAs($resident);
        $this->getJson('/api/resident/notices')->assertOk()->assertJsonCount(0, 'data');
        $this->getJson('/api/resident/notices/'.$notice->id)->assertForbidden();

        Sanctum::actingAs($admin);
        $this->getJson('/api/admin/notices/'.$notice->id)->assertOk();
    }

    public function test_admin_can_view_date_filtered_reports_and_other_roles_cannot(): void
    {
        $admin = User::create([
            'name' => 'Reporting Admin',
            'email' => 'reporting-admin@test.local',
            'password' => 'Password123!',
            'role' => User::ROLE_ADMIN,
        ]);
        Sanctum::actingAs($admin);
        $this->getJson('/api/admin/reports?range=THIS_MONTH')
            ->assertOk()
            ->assertJsonPath('data.range.label', 'This month')
            ->assertJsonStructure([
                'data' => ['maintenance', 'complaints', 'visitors', 'occupancy', 'parking', 'staff'],
            ]);

        $staff = User::create([
            'name' => 'Not a reporting admin',
            'email' => 'not-reporting-admin@test.local',
            'password' => 'Password123!',
            'role' => User::ROLE_STAFF,
        ]);
        Sanctum::actingAs($staff);
        $this->getJson('/api/admin/reports')->assertForbidden();
    }
}
