<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Notice extends Model
{
    public const AUDIENCE_ALL = 'ALL';

    public const AUDIENCE_RESIDENTS = 'RESIDENTS';

    public const AUDIENCE_OWNERS = 'OWNERS';

    public const AUDIENCE_STAFF = 'STAFF';

    protected $fillable = [
        'created_by_user_id',
        'title',
        'content',
        'category',
        'priority',
        'audience',
        'is_published',
        'published_at',
        'expires_at',
        'attachment_path',
    ];

    protected function casts(): array
    {
        return [
            'is_published' => 'boolean',
            'published_at' => 'datetime',
            'expires_at' => 'datetime',
        ];
    }

    public function createdBy()
    {
        return $this->belongsTo(User::class, 'created_by_user_id');
    }

    public function scopePublished($query)
    {
        return $query
            ->where('is_published', true)
            ->where(function ($inner): void {
                $inner->whereNull('published_at')->orWhere('published_at', '<=', now());
            })
            ->where(function ($inner): void {
                $inner->whereNull('expires_at')->orWhere('expires_at', '>=', now());
            });
    }

    public function scopeForAudience($query, string $audience)
    {
        return $query->where(function ($inner) use ($audience): void {
            $inner->where('audience', self::AUDIENCE_ALL)
                ->orWhere('audience', $audience);
        });
    }
}
