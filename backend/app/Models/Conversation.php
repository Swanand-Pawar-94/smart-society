<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Conversation extends Model
{
    public const TYPE_DIRECT = 'DIRECT';

    public const TYPE_GROUP = 'GROUP';

    protected $fillable = [
        'type',
        'subject',
        'created_by_user_id',
    ];

    public function createdBy()
    {
        return $this->belongsTo(User::class, 'created_by_user_id');
    }

    public function participants()
    {
        return $this->hasMany(ConversationParticipant::class);
    }

    public function messages()
    {
        return $this->hasMany(Message::class);
    }
}
