<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Hash;

class CreateInitialAdmin extends Command
{
    protected $signature = 'society:create-admin';

    protected $description = 'Interactively create an initial administrator account.';

    public function handle(): int
    {
        $name = trim((string) $this->ask('Name'));
        $email = strtolower(trim((string) $this->ask('Email')));
        $password = (string) $this->secret('Password');

        if ($name === '') {
            $this->error('Name is required.');

            return self::FAILURE;
        }

        if (! filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $this->error('A valid email address is required.');

            return self::FAILURE;
        }

        if (mb_strlen($password) < 8) {
            $this->error('Password must contain at least 8 characters.');

            return self::FAILURE;
        }

        if (User::query()->where('email', $email)->exists()) {
            $this->error('A user with this email address already exists.');

            return self::FAILURE;
        }

        $user = User::create([
            'name' => $name,
            'email' => $email,
            'password' => Hash::make($password),
            'role' => User::ROLE_ADMIN,
        ]);

        $this->info("Administrator account created for {$user->email}.");

        return self::SUCCESS;
    }
}
