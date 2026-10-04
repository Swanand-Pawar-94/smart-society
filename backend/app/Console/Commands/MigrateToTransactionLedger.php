<?php

namespace App\Console\Commands;

use App\Models\MaintenanceBill;
use App\Models\MaintenancePayment;
use App\Models\Resident;
use App\Models\ResidentTransaction;
use App\Services\TransactionLedgerService;
use Illuminate\Console\Command;

class MigrateToTransactionLedger extends Command
{
    protected $signature = 'ledger:migrate {--resident-id= : Migrate for specific resident only}';

    protected $description = 'Migrate existing maintenance bills and payments to transaction ledger';

    public function handle(TransactionLedgerService $ledger): int
    {
        $this->info('Starting transaction ledger migration...');

        $residentQuery = Resident::query();
        if ($residentId = $this->option('resident-id')) {
            $residentQuery->where('id', $residentId);
        }

        $residents = $residentQuery->get();
        $this->info("Processing {$residents->count()} residents...");

        $progressBar = $this->output->createProgressBar($residents->count());

        foreach ($residents as $resident) {
            $this->migrateResidentData($resident, $ledger);
            $progressBar->advance();
        }

        $progressBar->finish();
        $this->newLine(2);
        $this->info('Migration completed successfully!');

        return self::SUCCESS;
    }

    private function migrateResidentData(Resident $resident, TransactionLedgerService $ledger): void
    {
        // Get all bills for this resident's flat
        $bills = MaintenanceBill::where('flat_id', $resident->flat_id)
            ->orderBy('created_at')
            ->get();

        foreach ($bills as $bill) {
            if (!$ledger->transactionsExistForBill($bill->id)) {
                $ledger->createDebitFromBill($bill);
            }
        }

        // Get all payments for this resident
        $payments = MaintenancePayment::where('resident_id', $resident->id)
            ->where('status', MaintenancePayment::STATUS_COMPLETED)
            ->orderBy('paid_at')
            ->orderBy('created_at')
            ->get();

        foreach ($payments as $payment) {
            if (!$ledger->transactionExistsForPayment($payment->id)) {
                $ledger->createCreditFromPayment($payment);
            }
        }

        // Recalculate balances to ensure accuracy
        $ledger->recalculateBalances($resident->id);
    }
}
