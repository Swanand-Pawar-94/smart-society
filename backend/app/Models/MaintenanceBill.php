<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class MaintenanceBill extends Model
{
    public const STATUS_UNPAID = 'UNPAID';

    public const STATUS_PARTIALLY_PAID = 'PARTIALLY_PAID';

    public const STATUS_PAID = 'PAID';

    public const STATUS_OVERDUE = 'OVERDUE';

    public const STATUS_CANCELLED = 'CANCELLED';

    protected $fillable = [
        'flat_id',
        'billing_month',
        'billing_period_start',
        'billing_period_end',
        'due_date',
        'base_maintenance',
        'water_charge',
        'electricity_common_area_charge',
        'parking_charge',
        'other_charges',
        'late_fee',
        'discount',
        'amount',
        'status',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'billing_month' => 'date',
            'billing_period_start' => 'date',
            'billing_period_end' => 'date',
            'due_date' => 'date',
            'base_maintenance' => 'decimal:2',
            'water_charge' => 'decimal:2',
            'electricity_common_area_charge' => 'decimal:2',
            'parking_charge' => 'decimal:2',
            'other_charges' => 'decimal:2',
            'late_fee' => 'decimal:2',
            'discount' => 'decimal:2',
            'amount' => 'decimal:2',
        ];
    }

    public function flat()
    {
        return $this->belongsTo(Flat::class);
    }

    public function payments()
    {
        return $this->hasMany(MaintenancePayment::class);
    }
}
