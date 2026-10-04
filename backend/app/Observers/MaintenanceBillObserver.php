<?php

namespace App\Observers;

use App\Models\MaintenanceBill;
use App\Services\TransactionLedgerService;

class MaintenanceBillObserver
{
    public function __construct(
        private readonly TransactionLedgerService $ledger,
    ) {}

    /**
     * Handle the MaintenanceBill "created" event.
     */
    public function created(MaintenanceBill $bill): void
    {
        // Only create transactions if bill has an amount
        if ($bill->amount > 0 && !$this->ledger->transactionsExistForBill($bill->id)) {
            $this->ledger->createDebitFromBill($bill);
        }
    }
}
