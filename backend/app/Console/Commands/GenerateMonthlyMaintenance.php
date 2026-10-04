<?php

namespace App\Console\Commands;

use App\Services\Maintenance\MaintenanceService;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;

class GenerateMonthlyMaintenance extends Command
{
    protected $signature = 'maintenance:generate {--month= : The billing month format YYYY-MM} {--amount= : The maintenance amount}';

    protected $description = 'Automatically generate monthly maintenance bills for all society flats.';

    public function handle(MaintenanceService $service): int
    {
        $month = $this->option('month');
        $amount = $this->option('amount') ? (float) $this->option('amount') : null;

        if ($month !== null && ! preg_match('/^\d{4}-(0[1-9]|1[0-2])$/', $month)) {
            $this->error('The --month option must use YYYY-MM, for example 2026-09.');

            return Command::INVALID;
        }

        $label = $month
            ? Carbon::createFromFormat('!Y-m', $month)->format('F Y')
            : now()->startOfMonth()->format('F Y');
        $this->info("Generating maintenance for {$label}...");

        $result = $service->generateMonthlyInvoices($month, $amount);

        $this->info("Total eligible occupied flats found: {$result['total_flats']}");
        $this->info("{$result['created_count']} bills created");
        $this->info("{$result['skipped_count']} bills already existed");

        if (! empty($result['details'])) {
            $this->newLine();
            $this->table(
                ['Flat Number', 'Status', 'Details / Reason'],
                array_map(function ($d) {
                    $reason = $d['status'] === 'CREATED'
                        ? "Bill #{$d['bill_id']} created (₹{$d['amount']})"
                        : $d['reason'];

                    return [$d['flat_number'], $d['status'], $reason];
                }, $result['details'])
            );
        }

        return Command::SUCCESS;
    }
}
