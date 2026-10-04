<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Log;

class ScheduleTestCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'schedule:test {--date=2026-12-01 00:00:00 : Simulated date/time to evaluate} {--timezone=Asia/Kolkata : Target timezone}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Simulate a specific date and time to test scheduled tasks without modifying system clock';

    /**
     * Execute the console command.
     */
    public function handle(Schedule $schedule): int
    {
        $dateStr = $this->option('date');
        $tz = $this->option('timezone') ?: config('app.timezone', 'Asia/Kolkata');

        $simulatedTime = Carbon::parse($dateStr, $tz);
        Carbon::setTestNow($simulatedTime);

        $this->info("=================================================");
        $this->info(" SmartSociety Backend - Scheduler Test Simulator");
        $this->info("=================================================");
        $this->info("Simulated Time : " . $simulatedTime->toDateTimeString() . " (" . $tz . ")");
        $this->info("System Clock   : " . now()->toDateTimeString() . " (Unchanged)");
        $this->newLine();

        Log::info('[SchedulerTest] Simulating scheduler evaluation', [
            'simulated_time' => $simulatedTime->toDateTimeString(),
            'timezone'       => $tz,
        ]);

        $events = $schedule->events();
        $dueCount = 0;

        foreach ($events as $event) {
            $isDue = $event->isDue(app());
            $command = $event->command ?? $event->description ?? 'Closure Task';

            if ($isDue) {
                $dueCount++;
                $this->info("✓ TASK DUE: {$command}");
                $this->info("Executing scheduled task under simulated time...");
                $this->newLine();

                // Execute the scheduled task
                $event->run(app());
            } else {
                $this->comment("✗ Task Not Due: {$command} (Expression: {$event->expression})");
            }
        }

        $this->newLine();
        $this->info("Scheduler Simulation Finished. Total Due Tasks Executed: {$dueCount}");

        // Reset Carbon test time
        Carbon::setTestNow();

        return Command::SUCCESS;
    }
}
