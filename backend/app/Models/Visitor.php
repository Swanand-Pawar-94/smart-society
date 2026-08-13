<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Visitor extends Model
{
    use HasFactory;

    public const TYPE_GUEST = 'GUEST';

    public const TYPE_DELIVERY = 'DELIVERY';

    public const TYPE_CAB = 'CAB';

    public const TYPE_SERVICE_PROVIDER = 'SERVICE_PROVIDER';

    public const TYPE_OTHER = 'OTHER';

    public const APPROVAL_PENDING = 'PENDING';

    public const APPROVAL_APPROVED = 'APPROVED';

    public const APPROVAL_REJECTED = 'REJECTED';

    public const APPROVAL_EXPIRED = 'EXPIRED';

    public const APPROVAL_COMPLETED = 'COMPLETED';

    public const ENTRY_EXPECTED = 'EXPECTED';

    public const ENTRY_WAITING = 'WAITING';

    public const ENTRY_ENTERED = 'ENTERED';

    public const ENTRY_EXITED = 'EXITED';

    protected $fillable = [
        'visitor_name',
        'mobile_number',
        'purpose',
        'flat_id',
        'resident_id',
        'vehicle_number',
        'visitor_type',
        'entry_status',
        'approval_status',
        'expected_at',
        'entered_at',
        'exited_at',
        'approved_at',
        'approval_note',
        'is_pre_approved',
        'created_by_security_id',
        'approved_by_resident_id',
    ];

    protected function casts(): array
    {
        return [
            'expected_at' => 'datetime',
            'entered_at' => 'datetime',
            'exited_at' => 'datetime',
            'approved_at' => 'datetime',
            'is_pre_approved' => 'boolean',
        ];
    }

    public function flat()
    {
        return $this->belongsTo(Flat::class);
    }

    public function resident()
    {
        return $this->belongsTo(Resident::class);
    }

    public function createdBySecurity()
    {
        return $this->belongsTo(User::class, 'created_by_security_id');
    }

    public function approvedByResident()
    {
        return $this->belongsTo(Resident::class, 'approved_by_resident_id');
    }

    public function scopeSearch(Builder $query, ?string $search): Builder
    {
        if (blank($search)) {
            return $query;
        }

        return $query->where(function (Builder $query) use ($search): void {
            $query->where('visitor_name', 'like', "%{$search}%")
                ->orWhere('mobile_number', 'like', "%{$search}%")
                ->orWhere('vehicle_number', 'like', "%{$search}%");
        });
    }
}
