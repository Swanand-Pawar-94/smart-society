<?php

namespace App\Observers;

use App\Models\MaintenancePayment;
use App\Services\TransactionLedgerService;

class MaintenancePaymentObserver
{
    public function __construct(
        private readonly TransactionLedgerService $ledger,
    ) {}

    /**
     * Handle the MaintenancePayment "updated" event.
     * We use updated instead of created because payments are created as PENDING
     * and then updated to COMPLETED after verification.
     */
    public function updated(MaintenancePayment $payment): void
    {
        // Only create transaction when payment is marked as completed
        if ($payment->status === MaintenancePayment::STATUS_COMPLETED 
            && !$this->ledger->transactionExistsForPayment($payment->id)
        ) {
            $this->ledger->createCreditFromPayment($payment);
        }
    }
}
