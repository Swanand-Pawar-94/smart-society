<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class MaintenancePayment extends Model
{
    public const STATUS_PENDING = 'PENDING';

    public const STATUS_COMPLETED = 'COMPLETED';

    public const STATUS_FAILED = 'FAILED';

    public const STATUS_REFUNDED = 'REFUNDED';

    public const METHOD_CASH = 'CASH';

    public const METHOD_BANK_TRANSFER = 'BANK_TRANSFER';

    public const METHOD_UPI = 'UPI';

    public const METHOD_CARD = 'CARD';

    public const METHOD_CREDIT_CARD = 'CREDIT_CARD';

    public const METHOD_DEBIT_CARD = 'DEBIT_CARD';

    public const METHOD_NET_BANKING = 'NET_BANKING';

    public const METHOD_OTHER = 'OTHER';

    public static function paymentMethods(): array
    {
        return [
            self::METHOD_CASH,
            self::METHOD_BANK_TRANSFER,
            self::METHOD_UPI,
            self::METHOD_CARD,
            self::METHOD_CREDIT_CARD,
            self::METHOD_DEBIT_CARD,
            self::METHOD_NET_BANKING,
            self::METHOD_OTHER,
        ];
    }

    protected $fillable = ['payment_order_id', 'maintenance_bill_id', 'resident_id', 'amount', 'reference_id', 'payment_method', 'status', 'paid_at', 'receipt_reference'];

    protected function casts(): array
    {
        return ['amount' => 'decimal:2', 'paid_at' => 'datetime'];
    }

    public function bill()
    {
        return $this->belongsTo(MaintenanceBill::class, 'maintenance_bill_id');
    }

    public function resident()
    {
        return $this->belongsTo(Resident::class);
    }

    public function paymentOrder()
    {
        return $this->belongsTo(PaymentOrder::class);
    }
}
