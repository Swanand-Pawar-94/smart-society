<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Parcel extends Model
{
    public const STATUS_RECEIVED = 'RECEIVED';

    public const STATUS_AWAITING_PICKUP = 'AWAITING_PICKUP';

    public const STATUS_COLLECTED = 'COLLECTED';

    public const STATUS_RETURNED = 'RETURNED';

    public const STATUS_CANCELLED = 'CANCELLED';

    protected $fillable = [
        'flat_id', 'resident_id', 'received_by_user_id', 'collected_by_user_id',
        'courier_name', 'tracking_number', 'parcel_type', 'status', 'received_at',
        'collected_at', 'notes',
    ];

    protected function casts(): array
    {
        return ['received_at' => 'datetime', 'collected_at' => 'datetime'];
    }

    public function flat()
    {
        return $this->belongsTo(Flat::class);
    }

    public function resident()
    {
        return $this->belongsTo(Resident::class);
    }

    public function receivedBy()
    {
        return $this->belongsTo(User::class, 'received_by_user_id');
    }

    public function collectedBy()
    {
        return $this->belongsTo(User::class, 'collected_by_user_id');
    }
}
