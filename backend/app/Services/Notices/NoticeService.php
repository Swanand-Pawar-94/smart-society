<?php

namespace App\Services\Notices;

use App\Models\Notice;
use App\Models\User;
use App\Notifications\SocietyAlert;
use Illuminate\Support\Arr;

class NoticeService
{
    public function create(User $user, array $data): Notice
    {
        $payload = Arr::only($data, [
            'title', 'content', 'category', 'priority', 'audience', 'is_published', 'published_at', 'expires_at',
        ]);
        $payload['created_by_user_id'] = $user->id;

        if (! empty($payload['is_published'])) {
            $payload['published_at'] ??= now();
        }

        $notice = Notice::create($payload);
        $this->notifyAudience($notice);

        return $notice;
    }

    public function update(Notice $notice, array $data): Notice
    {
        $wasPublished = $notice->is_published;
        $payload = Arr::only($data, [
            'title', 'content', 'category', 'priority', 'audience', 'is_published', 'published_at', 'expires_at',
        ]);

        if (array_key_exists('is_published', $payload)) {
            if ($payload['is_published'] && ! $notice->is_published) {
                // A future publication date keeps a notice scheduled instead of
                // publishing it immediately when an administrator edits it.
                $payload['published_at'] ??= now();
            }

            if (! $payload['is_published']) {
                $payload['published_at'] = null;
            }
        }

        $notice->update($payload);

        $updated = $notice->fresh('createdBy');
        $this->notifyAudience($updated, ! $wasPublished && $updated->is_published);

        return $updated;
    }

    private function notifyAudience(Notice $notice, bool $publicationChanged = true): void
    {
        if (! $notice->is_published || ! $publicationChanged
            || ($notice->published_at && $notice->published_at->isFuture())) {
            return;
        }

        $users = User::query()
            ->when($notice->audience === Notice::AUDIENCE_RESIDENTS, fn ($query) => $query->where('role', User::ROLE_RESIDENT))
            ->when($notice->audience === Notice::AUDIENCE_STAFF, fn ($query) => $query->whereIn('role', [User::ROLE_STAFF, User::ROLE_SECURITY]))
            ->when($notice->audience === Notice::AUDIENCE_OWNERS, fn ($query) => $query->whereHas('resident', fn ($residents) => $residents->where('relation_to_owner', 'OWNER')))
            ->when($notice->audience === Notice::AUDIENCE_ALL, fn ($query) => $query->whereIn('role', [User::ROLE_ADMIN, User::ROLE_RESIDENT, User::ROLE_STAFF, User::ROLE_SECURITY]))
            ->get();

        $users->each(fn (User $user) => $user->notify(new SocietyAlert(
            'notice_published',
            'New society notice',
            $notice->title,
            ['notice_id' => $notice->id, 'audience' => $notice->audience],
        )));
    }
}
