<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AmenityBooking extends Model
{
    public const STATUS_PENDING = 'PENDING';

    public const STATUS_APPROVED = 'APPROVED';

    public const STATUS_CONFIRMED = 'CONFIRMED';

    public const STATUS_REJECTED = 'REJECTED';

    public const STATUS_CANCELLED = 'CANCELLED';

    public const STATUS_COMPLETED = 'COMPLETED';

    protected $fillable = [
        'amenity_id',
        'resident_id',
        'flat_id',
        'booking_date',
        'start_time',
        'end_time',
        'status',
        'cancelled_at',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'booking_date' => 'date',
            'start_time' => 'datetime:H:i',
            'end_time' => 'datetime:H:i',
            'cancelled_at' => 'datetime',
        ];
    }

    public function amenity()
    {
        return $this->belongsTo(Amenity::class);
    }

    public function resident()
    {
        return $this->belongsTo(Resident::class);
    }

    public function flat()
    {
        return $this->belongsTo(Flat::class);
    }
}
