<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class UserProfile extends Model
{
    protected $fillable = [
        'user_id',
        'date_of_birth',
        'gender',
        'emergency_contact',
        'profile_photo_path',
    ];

    protected function casts(): array
    {
        return ['date_of_birth' => 'date'];
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
