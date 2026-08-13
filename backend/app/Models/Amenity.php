<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Amenity extends Model
{
    protected $fillable = [
        'name',
        'description',
        'is_active',
        'max_booking_hours',
        'opening_time',
        'closing_time',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'opening_time' => 'datetime:H:i',
            'closing_time' => 'datetime:H:i',
        ];
    }

    public function bookings()
    {
        return $this->hasMany(AmenityBooking::class);
    }
}
