<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PaymentReceipt extends Model
{
    protected $fillable = [
        'payment_order_id',
        'receipt_number',
        'amount',
        'currency',
        'payment_method',
        'payment_reference',
        'issued_at',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'issued_at' => 'datetime',
        ];
    }

    public function paymentOrder()
    {
        return $this->belongsTo(PaymentOrder::class);
    }
}
