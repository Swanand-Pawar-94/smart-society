<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Complaint extends Model
{
    public const PRIORITY_LOW = 'LOW';

    public const PRIORITY_MEDIUM = 'MEDIUM';

    public const PRIORITY_HIGH = 'HIGH';

    public const PRIORITY_URGENT = 'URGENT';

    public const PRIORITY_EMERGENCY = 'EMERGENCY';

    public const STATUS_OPEN = 'OPEN';

    public const STATUS_ASSIGNED = 'ASSIGNED';

    public const STATUS_IN_PROGRESS = 'IN_PROGRESS';

    public const STATUS_RESOLVED = 'RESOLVED';

    public const STATUS_CLOSED = 'CLOSED';

    public const STATUS_REOPENED = 'REOPENED';

    public static function categories(): array
    {
        return [
            'PLUMBING', 'ELECTRICITY', 'CLEANING', 'SECURITY', 'LIFT',
            'PARKING', 'WATER', 'NOISE', 'OTHER', 'MAINTENANCE',
        ];
    }

    protected $fillable = [
        'resident_id',
        'flat_id',
        'assigned_staff_id',
        'category',
        'title',
        'description',
        'priority',
        'status',
        'resolved_at',
        'closed_at',
    ];

    protected function casts(): array
    {
        return [
            'resolved_at' => 'datetime',
            'closed_at' => 'datetime',
        ];
    }

    public function resident()
    {
        return $this->belongsTo(Resident::class);
    }

    public function flat()
    {
        return $this->belongsTo(Flat::class);
    }

    public function assignedStaff()
    {
        return $this->belongsTo(StaffMember::class, 'assigned_staff_id');
    }

    public function statusHistories()
    {
        return $this->hasMany(ComplaintStatusHistory::class);
    }
}
