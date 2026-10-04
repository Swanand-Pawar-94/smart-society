<?php

namespace Database\Seeders;

use App\Models\StaffMember;
use App\Models\User;
use App\Models\UserProfile;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        // Create or update Security Team account (idempotent)
        $securityUser = User::updateOrCreate(
            ['email' => 'security@smartsociety.local'],
            [
                'name' => 'Test Security',
                'email' => 'security@smartsociety.local',
                'phone' => '+919999999999',
                'role' => User::ROLE_SECURITY,
                'password' => Hash::make('Security@123'),
                'email_verified_at' => now(),
            ]
        );

        // Create user profile if it doesn't exist
        UserProfile::updateOrCreate(
            ['user_id' => $securityUser->id],
            [
                'user_id' => $securityUser->id,
                'gender' => 'OTHER',
                'emergency_contact' => '+919999999998',
            ]
        );

        // Create staff member record if it doesn't exist
        StaffMember::updateOrCreate(
            ['user_id' => $securityUser->id],
            [
                'user_id' => $securityUser->id,
                'employee_id' => 'SEC001',
                'name' => 'Test Security',
                'mobile' => '+919999999999',
                'email' => 'security@smartsociety.local',
                'designation' => 'Security Guard',
                'shift' => 'ALL',
                'status' => StaffMember::STATUS_ACTIVE,
                'joining_date' => now()->subMonths(6),
                'emergency_contact' => '+919999999998',
            ]
        );

        // Create default admin account if running in development
        if (app()->environment('local')) {
            $adminUser = User::updateOrCreate(
                ['email' => 'admin@smartsociety.local'],
                [
                    'name' => 'Test Administrator',
                    'email' => 'admin@smartsociety.local',
                    'phone' => '+919999999990',
                    'role' => User::ROLE_ADMIN,
                    'password' => Hash::make('Admin@123'),
                    'email_verified_at' => now(),
                ]
            );

            UserProfile::updateOrCreate(
                ['user_id' => $adminUser->id],
                [
                    'user_id' => $adminUser->id,
                    'gender' => 'OTHER',
                ]
            );
        }
    }
}
