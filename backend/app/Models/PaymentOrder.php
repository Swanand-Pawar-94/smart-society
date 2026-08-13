<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PaymentOrder extends Model
{
    public const STATUS_PENDING = 'PENDING';

    public const STATUS_PROCESSING = 'PROCESSING';

    public const STATUS_SUCCESSFUL = 'SUCCESSFUL';

    public const STATUS_FAILED = 'FAILED';

    public const STATUS_CANCELLED = 'CANCELLED';

    protected $fillable = [
        'resident_id', 'flat_id', 'amount', 'payment_method', 'status',
        'provider', 'provider_reference', 'gateway_payment_id', 'verification_note', 'verified_at',
    ];

    protected function casts(): array
    {
        return ['amount' => 'decimal:2', 'verified_at' => 'datetime'];
    }

    public function resident()
    {
        return $this->belongsTo(Resident::class);
    }

    public function flat()
    {
        return $this->belongsTo(Flat::class);
    }

    public function items()
    {
        return $this->hasMany(PaymentOrderItem::class);
    }

    public function maintenancePayments()
    {
        return $this->hasMany(MaintenancePayment::class);
    }

    public function receipt()
    {
        return $this->hasOne(PaymentReceipt::class);
    }
}
