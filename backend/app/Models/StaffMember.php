<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class StaffMember extends Model
{
    public const STATUS_ACTIVE = 'ACTIVE';

    public const STATUS_INACTIVE = 'INACTIVE';

    public const STATUS_ON_LEAVE = 'ON_LEAVE';

    protected $fillable = [
        'user_id',
        'employee_id',
        'name',
        'mobile',
        'email',
        'designation',
        'shift',
        'status',
        'joining_date',
        'emergency_contact',
    ];

    protected function casts(): array
    {
        return [
            'joining_date' => 'date',
        ];
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function assignedComplaints()
    {
        return $this->hasMany(Complaint::class, 'assigned_staff_id');
    }
}
