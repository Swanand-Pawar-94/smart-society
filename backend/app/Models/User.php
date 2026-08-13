<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

#[Fillable(['name', 'email', 'phone', 'role', 'password'])]
#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasApiTokens, HasFactory, Notifiable;

    public const ROLE_ADMIN = 'ADMIN';

    public const ROLE_RESIDENT = 'RESIDENT';

    public const ROLE_SECURITY = 'SECURITY';

    public const ROLE_STAFF = 'STAFF';

    public function resident()
    {
        return $this->hasOne(Resident::class);
    }

    public function profile()
    {
        return $this->hasOne(UserProfile::class);
    }

    public function ownedFlats()
    {
        return $this->hasMany(Flat::class, 'owner_user_id');
    }

    public function createdVisitorRequests()
    {
        return $this->hasMany(Visitor::class, 'created_by_security_id');
    }

    public function staffMember()
    {
        return $this->hasOne(StaffMember::class);
    }

    public function conversationParticipants()
    {
        return $this->hasMany(ConversationParticipant::class);
    }

    public function sentMessages()
    {
        return $this->hasMany(Message::class, 'sender_id');
    }

    public function hasRole(string ...$roles): bool
    {
        return in_array($this->role, $roles, true);
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
        ];
    }
}
