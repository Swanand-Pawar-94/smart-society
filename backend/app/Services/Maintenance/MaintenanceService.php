<?php

namespace App\Services\Maintenance;

use App\Models\MaintenanceBill;
use App\Models\MaintenancePayment;
use App\Models\Resident;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class MaintenanceService
{
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
     * Creates a pending payment request for a resident. The amount is derived
     * from the billed balance and never accepted from the mobile client.
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

            $pending = $bill->payments()
                ->where('resident_id', $resident->id)
                ->where('status', MaintenancePayment::STATUS_PENDING)
                ->latest()
                ->first();

            if ($pending) {
                return $pending->fresh(['bill', 'resident']);
            }

            $payment = MaintenancePayment::create([
                'maintenance_bill_id' => $bill->id,
                'resident_id' => $resident->id,
                'amount' => $outstanding,
                'reference_id' => $data['reference_id'] ?? 'PAY-'.Str::upper(Str::random(12)),
                'payment_method' => $data['payment_method'],
                'status' => MaintenancePayment::STATUS_PENDING,
                'receipt_reference' => $data['receipt_reference'] ?? null,
            ]);

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
