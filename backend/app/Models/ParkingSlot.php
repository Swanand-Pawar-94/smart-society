<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ParkingSlot extends Model
{
    public const TYPE_CAR = 'CAR';

    public const TYPE_BIKE = 'BIKE';

    public const TYPE_VISITOR = 'VISITOR';

    public const TYPE_OTHER = 'OTHER';

    public const STATUS_AVAILABLE = 'AVAILABLE';

    public const STATUS_ASSIGNED = 'ASSIGNED';

    public const STATUS_MAINTENANCE = 'MAINTENANCE';

    protected $fillable = [
        'slot_number',
        'parking_type',
        'status',
        'flat_id',
        'vehicle_number',
        'vehicle_type',
        'vehicle_description',
    ];

    public function flat()
    {
        return $this->belongsTo(Flat::class);
    }
}
