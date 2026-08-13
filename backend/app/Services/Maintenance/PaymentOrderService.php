<?php

namespace App\Services\Maintenance;

use App\Models\MaintenanceBill;
use App\Models\MaintenancePayment;
use App\Models\PaymentOrder;
use App\Models\PaymentOrderItem;
use App\Models\PaymentReceipt;
use App\Models\Resident;
use App\Notifications\SocietyAlert;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class PaymentOrderService
{
    /**
     * Generates a deterministic demo reference for a given order so repeated
     * calls to confirmDemo produce the same reference and are idempotent via
     * the unique constraint on gateway_payment_id.
     */
    private static function demoRef(PaymentOrder $order): string
    {
        return sprintf('DEMO-%s-%06d', now()->format('Ymd'), $order->id);
    }

    /**
     * Returns the only client-facing due summary. Every amount is calculated
     * from bills and completed payments on the server.
     */
    public function summary(Resident $resident): array
    {
        $currentMonth = now()->startOfMonth();
        $items = MaintenanceBill::query()
            ->withSum([
                'payments as paid_amount' => fn ($query) => $query
                    ->where('status', MaintenancePayment::STATUS_COMPLETED),
            ], 'amount')
            ->where('flat_id', $resident->flat_id)
            ->whereNotIn('status', [MaintenanceBill::STATUS_CANCELLED])
            ->orderBy('billing_month')
            ->get()
            ->map(function (MaintenanceBill $bill): array {
                $outstanding = max(0, (float) $bill->amount - (float) ($bill->paid_amount ?? 0));

                return [
                    'bill' => $bill,
                    'outstanding' => $outstanding,
                    'factor' => (float) $bill->amount > 0 ? $outstanding / (float) $bill->amount : 0,
                ];
            })
            ->filter(fn (array $item) => $item['outstanding'] > 0)
            ->values();

        $currentItems = $items->filter(fn (array $item) => $item['bill']->billing_month->gte($currentMonth));
        $maintenance = $currentItems->sum(fn (array $item) => $this->componentAmount($item, 'base_maintenance'));
        $water = $currentItems->sum(fn (array $item) => $this->componentAmount($item, 'water_charge'));
        $electricity = $currentItems->sum(fn (array $item) => $this->componentAmount($item, 'electricity_common_area_charge'));
        $parking = $currentItems->sum(fn (array $item) => $this->componentAmount($item, 'parking_charge'));
        $other = $currentItems->sum(fn (array $item) => $this->componentAmount($item, 'other_charges'));
        $lateFee = $currentItems->sum(fn (array $item) => $this->componentAmount($item, 'late_fee'));
        $discount = $currentItems->sum(fn (array $item) => $this->componentAmount($item, 'discount'));

        // Bills created before component support have only `amount`. They are
        // correctly shown as maintenance instead of inventing a charge split.
        $legacyMaintenance = $currentItems
            ->filter(fn (array $item) => ! $this->hasChargeComponents($item['bill']))
            ->sum('outstanding');
        $previousDues = $items
            ->filter(fn (array $item) => $item['bill']->billing_month->lt($currentMonth))
            ->sum('outstanding');

        return [
            'currency' => 'INR',
            'as_of' => now()->toISOString(),
            'maintenance' => round($maintenance + $legacyMaintenance, 2),
            'water_charge' => round($water, 2),
            'electricity_common_area_charge' => round($electricity, 2),
            'parking_charge' => round($parking, 2),
            'other_charges' => round($other, 2),
            'late_fee' => round($lateFee, 2),
            'discount' => round($discount, 2),
            'previous_dues' => round($previousDues, 2),
            'total_due' => round($items->sum('outstanding'), 2),
            'bill_count' => $items->count(),
        ];
    }

    /**
     * Creates one server-calculated order for every outstanding bill of the
     * authenticated resident. It is intentionally not a payment confirmation.
     */
    public function initiateFullPayment(Resident $resident, string $method): PaymentOrder
    {
        return DB::transaction(function () use ($resident, $method): PaymentOrder {
            $existing = PaymentOrder::query()
                ->where('resident_id', $resident->id)
                ->whereIn('status', [PaymentOrder::STATUS_PENDING, PaymentOrder::STATUS_PROCESSING])
                ->latest()
                ->first();

            if ($existing) {
                return $existing->load('items.bill');
            }

            $bills = MaintenanceBill::query()
                ->where('flat_id', $resident->flat_id)
                ->whereNotIn('status', [MaintenanceBill::STATUS_CANCELLED])
                ->lockForUpdate()
                ->get();

            $items = $bills->map(function (MaintenanceBill $bill): array {
                $paid = (float) $bill->payments()
                    ->where('status', MaintenancePayment::STATUS_COMPLETED)
                    ->sum('amount');

                return [
                    'bill' => $bill,
                    'amount' => max(0, (float) $bill->amount - $paid),
                ];
            })->filter(fn (array $item) => $item['amount'] > 0)->values();

            if ($items->isEmpty()) {
                throw ValidationException::withMessages([
                    'payment' => 'There are no outstanding dues to pay.',
                ]);
            }

            $order = PaymentOrder::create([
                'resident_id' => $resident->id,
                'flat_id' => $resident->flat_id,
                'amount' => $items->sum('amount'),
                'payment_method' => $method,
                'status' => PaymentOrder::STATUS_PENDING,
                // A real provider fills these values later; this value makes
                // it impossible to mistake the current local flow for a charge.
                'provider' => 'UNCONFIGURED',
                'provider_reference' => 'ORDER-'.Str::upper(Str::random(12)),
            ]);

            foreach ($items as $item) {
                PaymentOrderItem::create([
                    'payment_order_id' => $order->id,
                    'maintenance_bill_id' => $item['bill']->id,
                    'amount' => $item['amount'],
                ]);
            }

            return $order->fresh('items.bill');
        });
    }

    /**
     * Confirms a payment order internally for demo/development environments
     * (PAYMENT_MODE=demo). No external gateway is contacted. The method is
     * idempotent: if the order is already SUCCESSFUL it returns it unchanged
     * so the resident cannot be double-charged.
     *
     * All downstream effects (MaintenancePayment records, bill status,
     * PaymentReceipt, and the success notification) go through the same
     * recordVerifiedOrderPayments() path used by the admin transition, so the
     * database remains fully consistent.
     *
     * @throws \Illuminate\Validation\ValidationException
     */
    public function confirmDemo(PaymentOrder $paymentOrder, int $residentId): PaymentOrder
    {
        return DB::transaction(function () use ($paymentOrder, $residentId): PaymentOrder {
            $order = PaymentOrder::query()
                ->with('items')
                ->lockForUpdate()
                ->findOrFail($paymentOrder->id);

            // Safety: only the owning resident may confirm their own order.
            if ((int) $order->resident_id !== $residentId) {
                throw ValidationException::withMessages([
                    'payment' => 'You do not have permission to confirm this payment order.',
                ]);
            }

            // Idempotency: already successful — return as-is, no duplicate work.
            if ($order->status === PaymentOrder::STATUS_SUCCESSFUL) {
                return $order->fresh([
                    'resident.user', 'flat', 'items.bill', 'maintenancePayments.bill', 'receipt',
                ]);
            }

            if (! in_array($order->status, [PaymentOrder::STATUS_PENDING, PaymentOrder::STATUS_PROCESSING], true)) {
                throw ValidationException::withMessages([
                    'payment' => 'Only a pending or processing order can be confirmed.',
                ]);
            }

            $demoRef = self::demoRef($order);

            // Record individual maintenance payments and update bill statuses.
            $this->recordVerifiedOrderPayments($order, $demoRef);

            $order->update([
                'status'             => PaymentOrder::STATUS_SUCCESSFUL,
                'gateway_payment_id' => $demoRef,
                'verification_note'  => 'Confirmed via internal demo payment (PAYMENT_MODE=demo).',
                'verified_at'        => now(),
            ]);

            PaymentReceipt::firstOrCreate(
                ['payment_order_id' => $order->id],
                [
                    'receipt_number'   => sprintf('REC-%s-%06d', now()->format('Ymd'), $order->id),
                    'amount'           => $order->amount,
                    'currency'         => 'INR',
                    'payment_method'   => $order->payment_method,
                    'payment_reference' => $demoRef,
                    'issued_at'        => now(),
                ],
            );

            $order->loadMissing('resident.user');
            $order->resident?->user?->notify(new SocietyAlert(
                'payment_successful',
                'Payment successful',
                'Your maintenance payment has been confirmed (demo mode).',
                ['payment_order_id' => $order->id, 'amount' => (float) $order->amount],
            ));

            return $order->fresh([
                'resident.user', 'flat', 'items.bill', 'maintenancePayments.bill', 'receipt',
            ]);
        });
    }

    /**
     * Reconciles an aggregate order only after an administrator verifies it.
     * A payment provider is deliberately not implied by this transition.
     */
    public function transition(PaymentOrder $paymentOrder, array $data): PaymentOrder
    {
        return DB::transaction(function () use ($paymentOrder, $data): PaymentOrder {
            $order = PaymentOrder::query()
                ->with('items')
                ->lockForUpdate()
                ->findOrFail($paymentOrder->id);
            $status = $data['status'];
            $allowed = match ($order->status) {
                PaymentOrder::STATUS_PENDING => [
                    PaymentOrder::STATUS_PROCESSING,
                    PaymentOrder::STATUS_SUCCESSFUL,
                    PaymentOrder::STATUS_FAILED,
                    PaymentOrder::STATUS_CANCELLED,
                ],
                PaymentOrder::STATUS_PROCESSING => [
                    PaymentOrder::STATUS_SUCCESSFUL,
                    PaymentOrder::STATUS_FAILED,
                    PaymentOrder::STATUS_CANCELLED,
                ],
                default => [],
            };

            if (! in_array($status, $allowed, true)) {
                throw ValidationException::withMessages([
                    'status' => 'Invalid payment-order status transition.',
                ]);
            }

            if ($status === PaymentOrder::STATUS_SUCCESSFUL) {
                $this->recordVerifiedOrderPayments($order, $data['gateway_payment_id']);
            }

            $order->update([
                'status' => $status,
                'gateway_payment_id' => $data['gateway_payment_id'] ?? $order->gateway_payment_id,
                'verification_note' => $data['verification_note'] ?? $order->verification_note,
                'verified_at' => $status === PaymentOrder::STATUS_SUCCESSFUL ? now() : $order->verified_at,
            ]);

            if ($status === PaymentOrder::STATUS_SUCCESSFUL) {
                PaymentReceipt::firstOrCreate(
                    ['payment_order_id' => $order->id],
                    [
                        'receipt_number' => sprintf('REC-%s-%06d', now()->format('Ymd'), $order->id),
                        'amount' => $order->amount,
                        'currency' => 'INR',
                        'payment_method' => $order->payment_method,
                        'payment_reference' => $data['gateway_payment_id'],
                        'issued_at' => now(),
                    ],
                );

                $order->loadMissing('resident.user');
                $order->resident?->user?->notify(new SocietyAlert(
                    'payment_successful',
                    'Payment successful',
                    'Your maintenance payment has been verified successfully.',
                    ['payment_order_id' => $order->id, 'amount' => (float) $order->amount],
                ));
            }

            return $order->fresh([
                'resident.user', 'flat', 'items.bill', 'maintenancePayments.bill', 'receipt',
            ]);
        });
    }

    public function cancelByResident(PaymentOrder $paymentOrder): PaymentOrder
    {
        return DB::transaction(function () use ($paymentOrder): PaymentOrder {
            $order = PaymentOrder::query()->lockForUpdate()->findOrFail($paymentOrder->id);

            if ($order->status !== PaymentOrder::STATUS_PENDING) {
                throw ValidationException::withMessages([
                    'status' => 'Only a pending payment order can be cancelled.',
                ]);
            }

            $order->update(['status' => PaymentOrder::STATUS_CANCELLED]);

            return $order->fresh('items.bill');
        });
    }

    private function recordVerifiedOrderPayments(PaymentOrder $order, string $gatewayPaymentId): void
    {
        foreach ($order->items as $item) {
            $bill = MaintenanceBill::query()->lockForUpdate()->findOrFail($item->maintenance_bill_id);
            $paid = (float) $bill->payments()
                ->where('status', MaintenancePayment::STATUS_COMPLETED)
                ->sum('amount');
            $outstanding = max(0, (float) $bill->amount - $paid);

            if (round($outstanding, 2) !== round((float) $item->amount, 2)) {
                throw ValidationException::withMessages([
                    'status' => 'This order can no longer be confirmed because one or more billed amounts changed. Cancel it and create a new order.',
                ]);
            }

            MaintenancePayment::query()
                ->where('maintenance_bill_id', $bill->id)
                ->where('resident_id', $order->resident_id)
                ->where('status', MaintenancePayment::STATUS_PENDING)
                ->update(['status' => MaintenancePayment::STATUS_FAILED]);

            MaintenancePayment::create([
                'payment_order_id' => $order->id,
                'maintenance_bill_id' => $bill->id,
                'resident_id' => $order->resident_id,
                'amount' => $item->amount,
                'reference_id' => sprintf('%s-BILL-%d', $order->provider_reference, $bill->id),
                'payment_method' => $order->payment_method,
                'status' => MaintenancePayment::STATUS_COMPLETED,
                'paid_at' => now(),
                'receipt_reference' => $gatewayPaymentId,
            ]);

            $this->refreshBillStatus($bill);
        }
    }

    private function refreshBillStatus(MaintenanceBill $bill): void
    {
        if ($bill->status === MaintenanceBill::STATUS_CANCELLED) {
            return;
        }

        $paid = (float) $bill->payments()
            ->where('status', MaintenancePayment::STATUS_COMPLETED)
            ->sum('amount');

        $bill->update([
            'status' => $paid >= (float) $bill->amount
                ? MaintenanceBill::STATUS_PAID
                : ($paid > 0 ? MaintenanceBill::STATUS_PARTIALLY_PAID : MaintenanceBill::STATUS_UNPAID),
        ]);
    }

    private function hasChargeComponents(MaintenanceBill $bill): bool
    {
        return collect([
            $bill->base_maintenance,
            $bill->water_charge,
            $bill->electricity_common_area_charge,
            $bill->parking_charge,
            $bill->other_charges,
            $bill->late_fee,
            $bill->discount,
        ])->contains(fn ($value) => (float) $value !== 0.0);
    }

    private function componentAmount(array $item, string $component): float
    {
        return (float) $item['bill']->{$component} * (float) $item['factor'];
    }
}
