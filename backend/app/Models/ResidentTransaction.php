<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ResidentTransaction extends Model
{
    public const TYPE_DEBIT = 'DEBIT';
    public const TYPE_CREDIT = 'CREDIT';

    public const CATEGORY_MAINTENANCE = 'MAINTENANCE';
    public const CATEGORY_WATER = 'WATER';
    public const CATEGORY_ELECTRICITY = 'ELECTRICITY';
    public const CATEGORY_PARKING = 'PARKING';
    public const CATEGORY_AMENITY = 'AMENITY';
    public const CATEGORY_LATE_FEE = 'LATE_FEE';
    public const CATEGORY_OTHER_CHARGE = 'OTHER_CHARGE';
    public const CATEGORY_DISCOUNT = 'DISCOUNT';
    public const CATEGORY_PAYMENT = 'PAYMENT';
    public const CATEGORY_REFUND = 'REFUND';
    public const CATEGORY_ADJUSTMENT = 'ADJUSTMENT';

    public const STATUS_COMPLETED = 'COMPLETED';
    public const STATUS_PENDING = 'PENDING';
    public const STATUS_FAILED = 'FAILED';
    public const STATUS_CANCELLED = 'CANCELLED';

    protected $fillable = [
        'resident_id',
        'flat_id',
        'transaction_type',
        'category',
        'description',
        'amount',
        'balance_after',
        'transaction_date',
        'reference_type',
        'reference_id',
        'reference_number',
        'payment_method',
        'status',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'balance_after' => 'decimal:2',
            'transaction_date' => 'date',
        ];
    }

    public function resident()
    {
        return $this->belongsTo(Resident::class);
    }

    public function flat()
    {
        return $this->belongsTo(Flat::class);
    }

    /**
     * Get the related model (MaintenanceBill, MaintenancePayment, etc.)
     */
    public function reference()
    {
        return $this->morphTo(__FUNCTION__, 'reference_type', 'reference_id');
    }
}
