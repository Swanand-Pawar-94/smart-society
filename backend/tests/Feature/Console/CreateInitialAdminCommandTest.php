<?php

namespace Tests\Feature\Console;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class CreateInitialAdminCommandTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_creates_an_administrator_with_a_hashed_password(): void
    {
        $this->artisan('society:create-admin')
            ->expectsQuestion('Name', 'Primary Admin')
            ->expectsQuestion('Email', 'admin@example.test')
            ->expectsQuestion('Password', 'SecurePass123!')
            ->expectsOutput('Administrator account created for admin@example.test.')
            ->doesntExpectOutput('SecurePass123!')
            ->assertExitCode(0);

        $admin = User::query()->where('email', 'admin@example.test')->firstOrFail();

        $this->assertSame(User::ROLE_ADMIN, $admin->role);
        $this->assertTrue(Hash::check('SecurePass123!', $admin->password));
    }

    public function test_it_rejects_an_existing_email_address(): void
    {
        User::create([
            'name' => 'Existing User',
            'email' => 'admin@example.test',
            'password' => 'Password123!',
            'role' => User::ROLE_RESIDENT,
        ]);

        $this->artisan('society:create-admin')
            ->expectsQuestion('Name', 'Primary Admin')
            ->expectsQuestion('Email', 'admin@example.test')
            ->expectsQuestion('Password', 'SecurePass123!')
            ->expectsOutput('A user with this email address already exists.')
            ->assertExitCode(1);

        $this->assertDatabaseCount('users', 1);
    }
}
