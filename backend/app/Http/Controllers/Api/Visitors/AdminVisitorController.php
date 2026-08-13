<?php

namespace App\Http\Controllers\Api\Visitors;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\Visitors\ListVisitorsRequest;
use App\Http\Resources\Api\Visitors\VisitorResource;
use App\Models\Visitor;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Gate;

class AdminVisitorController extends Controller
{
    public function index(ListVisitorsRequest $request): AnonymousResourceCollection
    {
        Gate::authorize('viewAny', Visitor::class);
        $filters = $request->validated();

        $visitors = Visitor::query()
            ->with('flat')
            ->search($filters['q'] ?? null)
            ->when($filters['approval_status'] ?? null, fn (Builder $query, string $status) => $query->where('approval_status', $status))
            ->when($filters['entry_status'] ?? null, fn (Builder $query, string $status) => $query->where('entry_status', $status))
            ->when($filters['visitor_type'] ?? null, fn (Builder $query, string $type) => $query->where('visitor_type', $type))
            ->when($filters['flat_id'] ?? null, fn (Builder $query, int $flatId) => $query->where('flat_id', $flatId))
            ->latest()
            ->paginate();

        return VisitorResource::collection($visitors);
    }

    public function show(Visitor $visitor): VisitorResource
    {
        Gate::authorize('view', $visitor);

        return new VisitorResource($visitor->load('flat'));
    }
}
