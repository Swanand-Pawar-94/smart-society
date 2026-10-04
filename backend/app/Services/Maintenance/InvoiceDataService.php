<?php

namespace App\Services\Maintenance;

use App\Models\MaintenanceBill;
use App\Models\MaintenancePayment;
use App\Models\Resident;

class InvoiceDataService
{
    /**
     * Builds the resident-facing document data from persisted billing and
     * settlement records. The mobile app only renders this payload; it does
     * not calculate dues or invoice amounts itself.
     */
    public function forResident(MaintenanceBill $bill, Resident $resident): array
    {
        $bill->loadMissing('flat');
        $resident->loadMissing(['user', 'flat']);

        $paidAmount = (float) $bill->payments()
            ->where('status', MaintenancePayment::STATUS_COMPLETED)
            ->sum('amount');
        $currentTotal = (float) $bill->amount;
        $currentOutstanding = max(0, $currentTotal - $paidAmount);
        $previousDues = $this->previousOutstanding($bill);
        $billingMonth = $bill->billing_month;
        $flat = $bill->flat;

        return [
            'invoice_number' => sprintf(
                'INV-%s-%06d',
                $billingMonth?->format('Ym') ?? 'UNDATED',
                $bill->id,
            ),
            'invoice_date' => ($bill->created_at ?? $billingMonth)?->toDateString(),
            'due_date' => $bill->due_date?->toDateString(),
            'invoice_period' => $billingMonth?->format('F Y'),
            'society' => [
                'name' => config('society.name') ?: config('app.name', 'Kasliwal Marvel (West)'),
                'address' => config('society.address'),
            ],
            'unit' => [
                'building' => $flat?->building,
                'flat_number' => $flat?->flat_number,
                'floor' => $flat?->floor,
            ],
            'resident' => [
                'name' => $resident->user?->name,
            ],
            'line_items' => $this->lineItems($bill),
            'current_total' => round($currentTotal, 2),
            'paid_amount' => round($paidAmount, 2),
            'current_outstanding' => round($currentOutstanding, 2),
            'previous_dues' => round($previousDues, 2),
            'previous_dues_as_of' => $billingMonth?->copy()->subDay()->toDateString(),
            'net_payable' => round($currentOutstanding + $previousDues, 2),
            'amount_in_words' => $this->amountInWords($currentOutstanding + $previousDues),
        ];
    }

    private function previousOutstanding(MaintenanceBill $bill): float
    {
        if (! $bill->billing_month) {
            return 0;
        }

        return (float) MaintenanceBill::query()
            ->withSum([
                'payments as paid_amount' => fn ($query) => $query
                    ->where('status', MaintenancePayment::STATUS_COMPLETED),
            ], 'amount')
            ->where('flat_id', $bill->flat_id)
            ->where('id', '!=', $bill->id)
            ->whereNotIn('status', [MaintenanceBill::STATUS_CANCELLED])
            ->whereDate('billing_month', '<', $bill->billing_month->toDateString())
            ->get()
            ->sum(fn (MaintenanceBill $previous) => max(
                0,
                (float) $previous->amount - (float) ($previous->paid_amount ?? 0),
            ));
    }

    /** @return list<array{account: string, rate: float|null, comment: string, amount: float}> */
    private function lineItems(MaintenanceBill $bill): array
    {
        $hasComponents = collect([
            $bill->base_maintenance,
            $bill->water_charge,
            $bill->electricity_common_area_charge,
            $bill->parking_charge,
            $bill->other_charges,
            $bill->late_fee,
            $bill->discount,
        ])->contains(fn ($value) => (float) $value !== 0.0);
        $baseMaintenance = $hasComponents
            ? (float) $bill->base_maintenance
            : (float) $bill->amount;

        $items = [[
            'account' => 'Maintenance Fee',
            'rate' => $baseMaintenance,
            'comment' => 'Monthly maintenance fee',
            'amount' => $baseMaintenance,
        ]];

        foreach ([
            ['Water Charge', 'water_charge', 'Water supply and common-area usage'],
            ['Common Electricity', 'electricity_common_area_charge', 'Common-area electricity'],
            ['Parking Charge', 'parking_charge', 'Parking allocation charge'],
            ['Other Charges', 'other_charges', 'Other society charges'],
        ] as [$account, $field, $comment]) {
            if ((float) $bill->{$field} !== 0.0) {
                $items[] = [
                    'account' => $account,
                    'rate' => null,
                    'comment' => $comment,
                    'amount' => (float) $bill->{$field},
                ];
            }
        }

        if ((float) $bill->late_fee !== 0.0) {
            $items[] = [
                'account' => 'Late Payment Charges',
                'rate' => null,
                'comment' => 'As per society billing rules',
                'amount' => (float) $bill->late_fee,
            ];
        }

        if ((float) $bill->discount !== 0.0) {
            $items[] = [
                'account' => 'Discount',
                'rate' => null,
                'comment' => 'Applied to this invoice',
                'amount' => -(float) $bill->discount,
            ];
        }

        return $items;
    }

    private function amountInWords(float $amount): string
    {
        $paise = (int) round($amount * 100);
        $rupees = intdiv($paise, 100);
        $remainder = $paise % 100;
        $words = $this->numberInWords($rupees).' rupees';

        return $remainder > 0
            ? ucfirst($words).' and '.$this->numberInWords($remainder).' paise only'
            : ucfirst($words).' only';
    }

    private function numberInWords(int $number): string
    {
        if ($number === 0) {
            return 'zero';
        }

        $parts = [];
        foreach ([10000000 => 'crore', 100000 => 'lakh', 1000 => 'thousand', 100 => 'hundred'] as $value => $label) {
            if ($number >= $value) {
                $parts[] = $this->numberInWords(intdiv($number, $value)).' '.$label;
                $number %= $value;
            }
        }

        if ($number > 0) {
            $parts[] = $this->underOneHundred($number);
        }

        return implode(' ', $parts);
    }

    private function underOneHundred(int $number): string
    {
        $words = [
            0 => 'zero', 1 => 'one', 2 => 'two', 3 => 'three', 4 => 'four', 5 => 'five',
            6 => 'six', 7 => 'seven', 8 => 'eight', 9 => 'nine', 10 => 'ten',
            11 => 'eleven', 12 => 'twelve', 13 => 'thirteen', 14 => 'fourteen',
            15 => 'fifteen', 16 => 'sixteen', 17 => 'seventeen', 18 => 'eighteen',
            19 => 'nineteen', 20 => 'twenty', 30 => 'thirty', 40 => 'forty',
            50 => 'fifty', 60 => 'sixty', 70 => 'seventy', 80 => 'eighty', 90 => 'ninety',
        ];

        return $number < 20 || $number % 10 === 0
            ? $words[$number]
            : $words[intdiv($number, 10) * 10].' '.$words[$number % 10];
    }
}
