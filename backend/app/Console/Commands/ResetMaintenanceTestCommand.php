<?php

namespace App\Console\Commands;

use App\Models\Flat;
use App\Models\MaintenanceBill;
use App\Models\MaintenancePayment;
use App\Models\PaymentOrder;
use App\Models\PaymentOrderItem;
use App\Models\PaymentReceipt;
use App\Models\Resident;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class ResetMaintenanceTestCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'maintenance:reset-test {--force : Force deletion without confirmation prompt}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Safely reset only maintenance bills and test invoice records without touching flats, residents, or users.';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $billsCount = MaintenanceBill::count();
        $paymentsCount = MaintenancePayment::count();
        $ordersCount = PaymentOrder::count();
        $orderItemsCount = PaymentOrderItem::count();
        $receiptsCount = PaymentReceipt::count();

        $this->info('====================================================');
        $this->info(' SmartSociety - Maintenance & Invoice Test Reset');
        $this->info('====================================================');
        $this->table(
            ['Table Name', 'Current Record Count', 'Action'],
            [
                ['maintenance_bills', $billsCount, 'DELETE & RESET AUTO_INCREMENT'],
                ['maintenance_payments', $paymentsCount, 'DELETE & RESET AUTO_INCREMENT'],
                ['payment_orders', $ordersCount, 'DELETE & RESET AUTO_INCREMENT'],
                ['payment_order_items', $orderItemsCount, 'DELETE & RESET AUTO_INCREMENT'],
                ['payment_receipts', $receiptsCount, 'DELETE & RESET AUTO_INCREMENT'],
            ]
        );

        if (! $this->option('force') && ! $this->confirm('Are you sure you want to delete ONLY these maintenance invoice records? (Flats, residents, and users will remain untouched)')) {
            $this->warn('Operation cancelled by user.');

            return Command::INVALID;
        }

        DB::statement('SET FOREIGN_KEY_CHECKS = 0');
        DB::table('payment_receipts')->truncate();
        DB::table('payment_order_items')->truncate();
        DB::table('maintenance_payments')->truncate();
        DB::table('payment_orders')->truncate();
        DB::table('maintenance_bills')->truncate();
        DB::statement('SET FOREIGN_KEY_CHECKS = 1');

        $this->newLine();
        $this->info('✓ Successfully cleaned all maintenance invoice records.');
        $this->newLine();

        $this->table(
            ['Entity', 'Remaining Count', 'Status'],
            [
                ['Maintenance Invoices (Bills)', MaintenanceBill::count(), '0 records (Clean slate)'],
                ['Occupied Flats', Flat::where('occupancy_status', 'OCCUPIED')->count(), 'Intact'],
                ['Total Flats', Flat::count(), 'Intact'],
                ['Residents', Resident::count(), 'Intact'],
                ['User Accounts', User::count(), 'Intact'],
            ]
        );

        $this->newLine();
        $this->info('Ready for automatic scheduler test.');

        return Command::SUCCESS;
    }
}
