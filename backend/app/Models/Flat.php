<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Flat extends Model
{
    use HasFactory;

    protected $fillable = [
        'flat_number',
        'building',
        'floor',
        'owner_user_id',
        'occupancy_status',
    ];

    public function owner()
    {
        return $this->belongsTo(User::class, 'owner_user_id');
    }

    public function residents()
    {
        return $this->hasMany(Resident::class);
    }

    public function visitors()
    {
        return $this->hasMany(Visitor::class);
    }

    public function maintenanceBills()
    {
        return $this->hasMany(MaintenanceBill::class);
    }

    public function parkingSlots()
    {
        return $this->hasMany(ParkingSlot::class);
    }

    public function complaints()
    {
        return $this->hasMany(Complaint::class);
    }
}
