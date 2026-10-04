<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Mail;

class SendTestEmailCommand extends Command
{
    protected $signature = 'mail:test {email : The recipient email address}';
    protected $description = 'Send a test email to verify SMTP configuration';

    public function handle(): int
    {
        $email = $this->argument('email');
        $this->info("Attempting to send test email to {$email} using " . config('mail.default') . " transport...");

        try {
            Mail::raw("Smart Society SMTP Test\n\nThis is a test email sent from the Smart Society application to verify SMTP delivery.\nTimestamp: " . now()->toDateTimeString(), function ($message) use ($email) {
                $message->to($email)
                    ->subject('Smart Society — SMTP Test Email');
            });

            $this->info("✓ Test email successfully dispatched to {$email}.");
            return Command::SUCCESS;
        } catch (\Throwable $e) {
            $this->error("✗ Failed to send email: " . $e->getMessage());
            return Command::FAILURE;
        }
    }
}