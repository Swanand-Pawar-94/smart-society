<?php

namespace App\Services;

use App\Models\Flat;
use App\Models\MaintenanceBill;
use App\Models\MaintenancePayment;
use App\Models\Resident;
use App\Models\ResidentTransaction;
use Illuminate\Support\Facades\DB;

class TransactionLedgerService
{
    /**
     * Create debit transactions for a maintenance bill
     */
    public function createDebitFromBill(MaintenanceBill $bill): void
    {
        $resident = $bill->flat->residents()->where('is_primary_contact', true)->first();
        if (!$resident) {
            // If no primary contact, get the first resident
            $resident = $bill->flat->residents()->first();
        }
        if (!$resident) {
            return;
        }

        DB::transaction(function () use ($bill, $resident) {
            $currentBalance = $this->getCurrentBalance($resident->id);
            $transactionDate = $bill->created_at->toDateString();

            // Create debit entries for each charge component
            if ($bill->base_maintenance > 0) {
                $currentBalance = $this->createTransaction([
                    'resident_id' => $resident->id,
                    'flat_id' => $bill->flat_id,
                    'transaction_type' => ResidentTransaction::TYPE_DEBIT,
                    'category' => ResidentTransaction::CATEGORY_MAINTENANCE,
                    'description' => 'Maintenance charges for ' . $bill->billing_month->format('F Y'),
                    'amount' => $bill->base_maintenance,
                    'balance_after' => $currentBalance + $bill->base_maintenance,
                    'transaction_date' => $transactionDate,
                    'reference_type' => MaintenanceBill::class,
                    'reference_id' => $bill->id,
                    'reference_number' => 'INV-' . $bill->id,
                    'status' => ResidentTransaction::STATUS_COMPLETED,
                ]);
            }

            if ($bill->water_charge > 0) {
                $currentBalance = $this->createTransaction([
                    'resident_id' => $resident->id,
                    'flat_id' => $bill->flat_id,
                    'transaction_type' => ResidentTransaction::TYPE_DEBIT,
                    'category' => ResidentTransaction::CATEGORY_WATER,
                    'description' => 'Water charges for ' . $bill->billing_month->format('F Y'),
                    'amount' => $bill->water_charge,
                    'balance_after' => $currentBalance + $bill->water_charge,
                    'transaction_date' => $transactionDate,
                    'reference_type' => MaintenanceBill::class,
                    'reference_id' => $bill->id,
                    'reference_number' => 'INV-' . $bill->id,
                    'status' => ResidentTransaction::STATUS_COMPLETED,
                ]);
            }

            if ($bill->electricity_common_area_charge > 0) {
                $currentBalance = $this->createTransaction([
                    'resident_id' => $resident->id,
                    'flat_id' => $bill->flat_id,
                    'transaction_type' => ResidentTransaction::TYPE_DEBIT,
                    'category' => ResidentTransaction::CATEGORY_ELECTRICITY,
                    'description' => 'Common area electricity charges for ' . $bill->billing_month->format('F Y'),
                    'amount' => $bill->electricity_common_area_charge,
                    'balance_after' => $currentBalance + $bill->electricity_common_area_charge,
                    'transaction_date' => $transactionDate,
                    'reference_type' => MaintenanceBill::class,
                    'reference_id' => $bill->id,
                    'reference_number' => 'INV-' . $bill->id,
                    'status' => ResidentTransaction::STATUS_COMPLETED,
                ]);
            }

            if ($bill->parking_charge > 0) {
                $currentBalance = $this->createTransaction([
                    'resident_id' => $resident->id,
                    'flat_id' => $bill->flat_id,
                    'transaction_type' => ResidentTransaction::TYPE_DEBIT,
                    'category' => ResidentTransaction::CATEGORY_PARKING,
                    'description' => 'Parking charges for ' . $bill->billing_month->format('F Y'),
                    'amount' => $bill->parking_charge,
                    'balance_after' => $currentBalance + $bill->parking_charge,
                    'transaction_date' => $transactionDate,
                    'reference_type' => MaintenanceBill::class,
                    'reference_id' => $bill->id,
                    'reference_number' => 'INV-' . $bill->id,
                    'status' => ResidentTransaction::STATUS_COMPLETED,
                ]);
            }

            if ($bill->other_charges > 0) {
                $currentBalance = $this->createTransaction([
                    'resident_id' => $resident->id,
                    'flat_id' => $bill->flat_id,
                    'transaction_type' => ResidentTransaction::TYPE_DEBIT,
                    'category' => ResidentTransaction::CATEGORY_OTHER_CHARGE,
                    'description' => 'Other charges for ' . $bill->billing_month->format('F Y'),
                    'amount' => $bill->other_charges,
                    'balance_after' => $currentBalance + $bill->other_charges,
                    'transaction_date' => $transactionDate,
                    'reference_type' => MaintenanceBill::class,
                    'reference_id' => $bill->id,
                    'reference_number' => 'INV-' . $bill->id,
                    'status' => ResidentTransaction::STATUS_COMPLETED,
                ]);
            }

            if ($bill->late_fee > 0) {
                $currentBalance = $this->createTransaction([
                    'resident_id' => $resident->id,
                    'flat_id' => $bill->flat_id,
                    'transaction_type' => ResidentTransaction::TYPE_DEBIT,
                    'category' => ResidentTransaction::CATEGORY_LATE_FEE,
                    'description' => 'Late payment fee for ' . $bill->billing_month->format('F Y'),
                    'amount' => $bill->late_fee,
                    'balance_after' => $currentBalance + $bill->late_fee,
                    'transaction_date' => $transactionDate,
                    'reference_type' => MaintenanceBill::class,
                    'reference_id' => $bill->id,
                    'reference_number' => 'INV-' . $bill->id,
                    'status' => ResidentTransaction::STATUS_COMPLETED,
                ]);
            }

            if ($bill->discount > 0) {
                $currentBalance = $this->createTransaction([
                    'resident_id' => $resident->id,
                    'flat_id' => $bill->flat_id,
                    'transaction_type' => ResidentTransaction::TYPE_CREDIT,
                    'category' => ResidentTransaction::CATEGORY_DISCOUNT,
                    'description' => 'Discount for ' . $bill->billing_month->format('F Y'),
                    'amount' => $bill->discount,
                    'balance_after' => $currentBalance - $bill->discount,
                    'transaction_date' => $transactionDate,
                    'reference_type' => MaintenanceBill::class,
                    'reference_id' => $bill->id,
                    'reference_number' => 'INV-' . $bill->id,
                    'status' => ResidentTransaction::STATUS_COMPLETED,
                ]);
            }
        });
    }

    /**
     * Create credit transaction for a payment
     */
    public function createCreditFromPayment(MaintenancePayment $payment): void
    {
        if ($payment->status !== MaintenancePayment::STATUS_COMPLETED) {
            return;
        }

        $currentBalance = $this->getCurrentBalance($payment->resident_id);
        $bill = $payment->bill;

        $this->createTransaction([
            'resident_id' => $payment->resident_id,
            'flat_id' => $payment->resident->flat_id,
            'transaction_type' => ResidentTransaction::TYPE_CREDIT,
            'category' => ResidentTransaction::CATEGORY_PAYMENT,
            'description' => 'Payment received for ' . ($bill ? $bill->billing_month->format('F Y') : 'maintenance'),
            'amount' => $payment->amount,
            'balance_after' => $currentBalance - $payment->amount,
            'transaction_date' => $payment->paid_at?->toDateString() ?? $payment->created_at->toDateString(),
            'reference_type' => MaintenancePayment::class,
            'reference_id' => $payment->id,
            'reference_number' => $payment->receipt_reference ?? $payment->reference_id,
            'payment_method' => $payment->payment_method,
            'status' => ResidentTransaction::STATUS_COMPLETED,
        ]);
    }

    /**
     * Get current balance for a resident
     */
    public function getCurrentBalance(int $residentId): float
    {
        $lastTransaction = ResidentTransaction::where('resident_id', $residentId)
            ->where('status', ResidentTransaction::STATUS_COMPLETED)
            ->orderBy('transaction_date', 'desc')
            ->orderBy('id', 'desc')
            ->first();

        return $lastTransaction ? (float) $lastTransaction->balance_after : 0.0;
    }

    /**
     * Create a transaction and return the new balance
     */
    private function createTransaction(array $data): float
    {
        $transaction = ResidentTransaction::create($data);
        return (float) $transaction->balance_after;
    }

    /**
     * Recalculate all balances for a resident (use when migrating existing data)
     */
    public function recalculateBalances(int $residentId): void
    {
        DB::transaction(function () use ($residentId) {
            $transactions = ResidentTransaction::where('resident_id', $residentId)
                ->where('status', ResidentTransaction::STATUS_COMPLETED)
                ->orderBy('transaction_date')
                ->orderBy('id')
                ->get();

            $runningBalance = 0.0;
            foreach ($transactions as $transaction) {
                if ($transaction->transaction_type === ResidentTransaction::TYPE_DEBIT) {
                    $runningBalance += (float) $transaction->amount;
                } else {
                    $runningBalance -= (float) $transaction->amount;
                }
                $transaction->balance_after = $runningBalance;
                $transaction->save();
            }
        });
    }

    /**
     * Check if transactions already exist for a bill
     */
    public function transactionsExistForBill(int $billId): bool
    {
        return ResidentTransaction::where('reference_type', MaintenanceBill::class)
            ->where('reference_id', $billId)
            ->exists();
    }

    /**
     * Check if transaction already exists for a payment
     */
    public function transactionExistsForPayment(int $paymentId): bool
    {
        return ResidentTransaction::where('reference_type', MaintenancePayment::class)
            ->where('reference_id', $paymentId)
            ->exists();
    }
}
