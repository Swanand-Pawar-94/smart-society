<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Resident extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'flat_id',
        'relation_to_owner',
        'is_primary_contact',
    ];

    protected function casts(): array
    {
        return [
            'is_primary_contact' => 'boolean',
        ];
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function flat()
    {
        return $this->belongsTo(Flat::class);
    }

    public function visitorRequests()
    {
        return $this->hasMany(Visitor::class);
    }

    public function maintenancePayments()
    {
        return $this->hasMany(MaintenancePayment::class);
    }

    public function complaints()
    {
        return $this->hasMany(Complaint::class);
    }

    public function amenityBookings()
    {
        return $this->hasMany(AmenityBooking::class);
    }
}
