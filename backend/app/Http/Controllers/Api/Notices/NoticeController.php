<?php

namespace App\Http\Controllers\Api\Notices;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\Notices\StoreNoticeRequest;
use App\Http\Requests\Api\Notices\UpdateNoticeRequest;
use App\Http\Resources\Api\Notices\NoticeResource;
use App\Models\Notice;
use App\Services\Notices\NoticeService;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Gate;

class NoticeController extends Controller
{
    public function __construct(private readonly NoticeService $notices) {}

    public function index(Request $request): AnonymousResourceCollection
    {
        $user = $request->user();
        $query = Notice::query()->latest();
        if (! $user->hasRole('ADMIN')) {
            abort_unless($user->hasRole('RESIDENT', 'STAFF', 'SECURITY'), 403);
            $query->published()->where(function ($audience) use ($user): void {
                $audience->where('audience', Notice::AUDIENCE_ALL);
                if ($user->hasRole('RESIDENT')) {
                    $audience->orWhere('audience', Notice::AUDIENCE_RESIDENTS);
                    if ($user->resident?->relation_to_owner === 'OWNER') {
                        $audience->orWhere('audience', Notice::AUDIENCE_OWNERS);
                    }
                } else {
                    $audience->orWhere('audience', Notice::AUDIENCE_STAFF);
                }
            });
        }

        return NoticeResource::collection($query->paginate());
    }

    public function store(StoreNoticeRequest $request): NoticeResource
    {
        Gate::authorize('manage', Notice::class);

        return new NoticeResource($this->notices->create($request->user(), $request->validated()));
    }

    public function show(Notice $notice): NoticeResource
    {
        Gate::authorize('view', $notice);

        return new NoticeResource($notice);
    }

    public function update(UpdateNoticeRequest $request, Notice $notice): NoticeResource
    {
        Gate::authorize('manage', Notice::class);

        return new NoticeResource($this->notices->update($notice, $request->validated()));
    }
}
