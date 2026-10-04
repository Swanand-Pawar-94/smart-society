<?php

namespace App\Services\Maintenance;

use App\Models\Flat;
use App\Models\MaintenanceBill;
use App\Models\MaintenancePayment;
use App\Models\Resident;
use App\Notifications\SocietyAlert;
use Illuminate\Database\QueryException;
use Illuminate\Support\Arr;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

use Illuminate\Support\Facades\Log;

class MaintenanceService
{
    public function generateMonthlyInvoices(?string $month = null, ?float $amount = null): array
    {
        $billingMonth = $month ? Carbon::parse($month)->startOfMonth() : now()->startOfMonth();
        $dueDate = $billingMonth->copy()->addDays(15);
        $invoiceAmount = $amount ?? (float) env('MONTHLY_MAINTENANCE_AMOUNT', 2000.00);

        $flats = Flat::query()
            ->where('occupancy_status', 'OCCUPIED')
            ->whereHas('residents')
            ->get();
        $createdCount = 0;
        $skippedCount = 0;
        $details = [];

        Log::info("Starting monthly maintenance generation for {$billingMonth->format('F Y')}", [
            'total_occupied_flats_with_residents' => $flats->count(),
            'amount_per_flat' => $invoiceAmount,
        ]);

        foreach ($flats as $flat) {
            $flatIdentifier = "Flat {$flat->flat_number} (ID #{$flat->id})";

            if (MaintenanceBill::query()
                ->where('flat_id', $flat->id)
                ->whereDate('billing_month', $billingMonth->toDateString())
                ->exists()) {
                $skippedCount++;
                $reason = "Bill for {$billingMonth->format('F Y')} already exists in database.";
                $details[] = [
                    'flat_id' => $flat->id,
                    'flat_number' => $flat->flat_number,
                    'status' => 'SKIPPED',
                    'reason' => $reason,
                ];
                Log::info("Skipped {$flatIdentifier}: {$reason}");

                continue;
            }

            $attributes = [
                'flat_id' => $flat->id,
                'billing_month' => $billingMonth->toDateString(),
                'billing_period_start' => $billingMonth->toDateString(),
                'billing_period_end' => $billingMonth->copy()->endOfMonth()->toDateString(),
                'due_date' => $dueDate->toDateString(),
                'base_maintenance' => $invoiceAmount,
                'water_charge' => 0,
                'electricity_common_area_charge' => 0,
                'parking_charge' => 0,
                'other_charges' => 0,
                'late_fee' => 0,
                'discount' => 0,
                'amount' => $invoiceAmount,
                'status' => MaintenanceBill::STATUS_UNPAID,
                'notes' => 'Automated monthly maintenance invoice for '.$billingMonth->format('F Y'),
            ];

            try {
                $bill = MaintenanceBill::create($attributes);
                $createdCount++;
                $details[] = [
                    'flat_id' => $flat->id,
                    'flat_number' => $flat->flat_number,
                    'status' => 'CREATED',
                    'bill_id' => $bill->id,
                    'amount' => $invoiceAmount,
                ];
                Log::info("Created monthly invoice #{$bill->id} for {$flatIdentifier} - Amount: ₹{$invoiceAmount}");
            } catch (QueryException $exception) {
                if (MaintenanceBill::query()
                    ->where('flat_id', $flat->id)
                    ->whereDate('billing_month', $billingMonth->toDateString())
                    ->exists()) {
                    $skippedCount++;
                    $details[] = [
                        'flat_id' => $flat->id,
                        'flat_number' => $flat->flat_number,
                        'status' => 'SKIPPED',
                        'reason' => 'Concurrent process created bill.',
                    ];

                    continue;
                }

                Log::error("Failed creating invoice for {$flatIdentifier}: ".$exception->getMessage());
                throw $exception;
            }
        }

        return [
            'billing_month' => $billingMonth->format('F Y'),
            'created_count' => $createdCount,
            'skipped_count' => $skippedCount,
            'total_flats' => $flats->count(),
            'amount_per_flat' => $invoiceAmount,
            'details' => $details,
        ];
    }

    public function createBill(array $data): MaintenanceBill
    {
        $exists = MaintenanceBill::query()
            ->where('flat_id', $data['flat_id'])
            ->whereDate('billing_month', $data['billing_month'])
            ->exists();

        if ($exists) {
            throw ValidationException::withMessages([
                'billing_month' => 'A maintenance bill already exists for this flat and billing month.',
            ]);
        }

        $bill = MaintenanceBill::create([
            ...Arr::only($data, [
                'flat_id', 'billing_month', 'billing_period_start', 'billing_period_end', 'due_date',
                'base_maintenance', 'water_charge', 'electricity_common_area_charge', 'parking_charge',
                'other_charges', 'late_fee', 'discount', 'notes',
            ]),
            'amount' => $this->amountFor($data),
            'status' => MaintenanceBill::STATUS_UNPAID,
        ]);

        $this->refreshBillStatus($bill);

        return $bill->fresh(['flat']);
    }

    public function updateBill(MaintenanceBill $bill, array $data): MaintenanceBill
    {
        $chargeFields = $this->chargeFields();
        if (in_array($bill->status, [MaintenanceBill::STATUS_PAID, MaintenanceBill::STATUS_CANCELLED], true)
            && (isset($data['amount']) || collect($chargeFields)->contains(fn (string $field) => array_key_exists($field, $data)))) {
            throw ValidationException::withMessages([
                'amount' => 'Cannot change amount on a fully paid bill.',
            ]);
        }

        if (($data['status'] ?? null) === MaintenanceBill::STATUS_CANCELLED
            && $bill->payments()->where('status', MaintenancePayment::STATUS_COMPLETED)->exists()) {
            throw ValidationException::withMessages([
                'status' => 'A bill with completed payments cannot be cancelled.',
            ]);
        }

        $attributes = Arr::only($data, [
            'billing_period_start', 'billing_period_end', 'due_date', 'notes', 'status',
            ...$chargeFields,
        ]);
        if (isset($data['amount']) && ! collect($chargeFields)->contains(fn (string $field) => array_key_exists($field, $data))) {
            $attributes['amount'] = $data['amount'];
        }
        if (collect($chargeFields)->contains(fn (string $field) => array_key_exists($field, $data))) {
            $attributes['amount'] = $this->amountFor([...$bill->only($chargeFields), ...$data]);
        }
        $bill->update($attributes);
        $this->refreshBillStatus($bill->fresh());

        return $bill->fresh(['flat', 'payments']);
    }

    public function syncOverdueBills(): void
    {
        MaintenanceBill::query()
            ->whereIn('status', [MaintenanceBill::STATUS_UNPAID, MaintenanceBill::STATUS_PARTIALLY_PAID])
            ->whereDate('due_date', '<', now()->toDateString())
            ->update(['status' => MaintenanceBill::STATUS_OVERDUE]);
    }

    public function recordPayment(array $data): MaintenancePayment
    {
        return DB::transaction(function () use ($data): MaintenancePayment {
            $bill = MaintenanceBill::lockForUpdate()->findOrFail($data['maintenance_bill_id']);

            if ($bill->flat_id !== $data['flat_id']) {
                throw ValidationException::withMessages([
                    'resident_id' => 'The resident must belong to the billed flat.',
                ]);
            }

            $paid = $bill->payments()
                ->where('status', MaintenancePayment::STATUS_COMPLETED)
                ->sum('amount');

            if (($paid + $data['amount']) > $bill->amount) {
                throw ValidationException::withMessages([
                    'amount' => 'Payment exceeds the outstanding bill amount.',
                ]);
            }

            $payment = MaintenancePayment::create([
                ...$data,
                'paid_at' => $data['status'] === MaintenancePayment::STATUS_COMPLETED ? now() : null,
            ]);

            if ($payment->status === MaintenancePayment::STATUS_COMPLETED) {
                $this->refreshBillStatus($bill);
            }

            return $payment->fresh(['bill', 'resident']);
        });
    }

    /**
     * Creates a payment record for a resident. In demo mode (PAYMENT_MODE=demo),
     * it immediately marks the payment completed and the bill paid without
     * requiring external gateway confirmation.
     */
    public function initiateResidentPayment(Resident $resident, array $data): MaintenancePayment
    {
        return DB::transaction(function () use ($resident, $data): MaintenancePayment {
            $bill = MaintenanceBill::query()->lockForUpdate()->findOrFail($data['maintenance_bill_id']);

            if ($bill->flat_id !== $resident->flat_id) {
                throw ValidationException::withMessages([
                    'maintenance_bill_id' => 'This bill does not belong to your flat.',
                ]);
            }

            $paid = (float) $bill->payments()
                ->where('status', MaintenancePayment::STATUS_COMPLETED)
                ->sum('amount');
            $outstanding = max(0, (float) $bill->amount - $paid);

            if ($outstanding <= 0) {
                throw ValidationException::withMessages([
                    'maintenance_bill_id' => 'This bill is already paid.',
                ]);
            }

            $isDemo = config('app.payment_mode', 'live') === 'demo';

            if (! $isDemo) {
                $pending = $bill->payments()
                    ->where('resident_id', $resident->id)
                    ->where('status', MaintenancePayment::STATUS_PENDING)
                    ->latest()
                    ->first();

                if ($pending) {
                    return $pending->fresh(['bill', 'resident']);
                }
            }

            $referenceId = $data['reference_id'] ?? 'PAY-'.Str::upper(Str::random(12));
            $receiptRef = $data['receipt_reference'] ?? ($isDemo ? sprintf('DEMO-%s-%06d', now()->format('Ymd'), $bill->id) : null);

            $payment = MaintenancePayment::create([
                'maintenance_bill_id' => $bill->id,
                'resident_id' => $resident->id,
                'amount' => $outstanding,
                'reference_id' => $referenceId,
                'payment_method' => $data['payment_method'],
                'status' => $isDemo ? MaintenancePayment::STATUS_COMPLETED : MaintenancePayment::STATUS_PENDING,
                'paid_at' => $isDemo ? now() : null,
                'receipt_reference' => $receiptRef,
            ]);

            if ($isDemo) {
                MaintenancePayment::query()
                    ->where('maintenance_bill_id', $bill->id)
                    ->where('id', '!=', $payment->id)
                    ->where('status', MaintenancePayment::STATUS_PENDING)
                    ->update(['status' => MaintenancePayment::STATUS_FAILED]);

                $this->refreshBillStatus($bill);

                $resident->user?->notify(new SocietyAlert(
                    'payment_successful',
                    'Payment successful',
                    'Your maintenance payment has been completed successfully (demo mode).',
                    ['maintenance_bill_id' => $bill->id, 'amount' => (float) $payment->amount],
                ));
            }

            return $payment->fresh(['bill', 'resident']);
        });
    }

    public function transition(MaintenancePayment $payment, string $status, ?string $receiptReference = null): MaintenancePayment
    {
        $allowed = [
            MaintenancePayment::STATUS_PENDING => [
                MaintenancePayment::STATUS_COMPLETED,
                MaintenancePayment::STATUS_FAILED,
            ],
            MaintenancePayment::STATUS_COMPLETED => [MaintenancePayment::STATUS_REFUNDED],
        ];

        if (! in_array($status, $allowed[$payment->status] ?? [], true)) {
            throw ValidationException::withMessages([
                'status' => 'Invalid payment status transition.',
            ]);
        }

        return DB::transaction(function () use ($payment, $status, $receiptReference): MaintenancePayment {
            $payment->update([
                'status' => $status,
                'paid_at' => $status === MaintenancePayment::STATUS_COMPLETED ? ($payment->paid_at ?? now()) : $payment->paid_at,
                'receipt_reference' => $receiptReference ?? $payment->receipt_reference,
            ]);

            $this->refreshBillStatus($payment->bill()->lockForUpdate()->first());

            return $payment->fresh(['bill', 'resident']);
        });
    }

    public function collectionSummary(): array
    {
        $this->syncOverdueBills();

        $bills = MaintenanceBill::query()->get(['status', 'amount']);
        $payments = MaintenancePayment::query()
            ->where('status', MaintenancePayment::STATUS_COMPLETED)
            ->get(['amount']);

        return [
            'total_billed' => (float) $bills->sum('amount'),
            'total_collected' => (float) $payments->sum('amount'),
            'paid_bills' => $bills->where('status', MaintenanceBill::STATUS_PAID)->count(),
            'unpaid_bills' => $bills->where('status', MaintenanceBill::STATUS_UNPAID)->count(),
            'partially_paid_bills' => $bills->where('status', MaintenanceBill::STATUS_PARTIALLY_PAID)->count(),
            'overdue_bills' => $bills->where('status', MaintenanceBill::STATUS_OVERDUE)->count(),
        ];
    }

    private function refreshBillStatus(MaintenanceBill $bill): void
    {
        if ($bill->status === MaintenanceBill::STATUS_CANCELLED) {
            return;
        }

        $paid = $bill->payments()
            ->where('status', MaintenancePayment::STATUS_COMPLETED)
            ->sum('amount');

        if ($paid >= $bill->amount) {
            $status = MaintenanceBill::STATUS_PAID;
        } elseif ($paid > 0) {
            $status = MaintenanceBill::STATUS_PARTIALLY_PAID;
        } elseif ($bill->due_date->isPast()) {
            $status = MaintenanceBill::STATUS_OVERDUE;
        } else {
            $status = MaintenanceBill::STATUS_UNPAID;
        }

        $bill->update(['status' => $status]);
    }

    private function amountFor(array $data): float
    {
        $chargeFields = $this->chargeFields();
        if (! collect($chargeFields)->contains(fn (string $field) => array_key_exists($field, $data))) {
            return round((float) $data['amount'], 2);
        }

        $positive = collect(array_slice($chargeFields, 0, -1))
            ->sum(fn (string $field) => (float) ($data[$field] ?? 0));

        return round(max(0, $positive - (float) ($data['discount'] ?? 0)), 2);
    }

    private function chargeFields(): array
    {
        return [
            'base_maintenance', 'water_charge', 'electricity_common_area_charge',
            'parking_charge', 'other_charges', 'late_fee', 'discount',
        ];
    }
}
