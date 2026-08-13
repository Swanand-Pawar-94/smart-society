<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ComplaintStatusHistory extends Model
{
    protected $fillable = [
        'complaint_id',
        'from_status',
        'to_status',
        'changed_by_user_id',
        'note',
    ];

    public function complaint()
    {
        return $this->belongsTo(Complaint::class);
    }

    public function changedBy()
    {
        return $this->belongsTo(User::class, 'changed_by_user_id');
    }
}
