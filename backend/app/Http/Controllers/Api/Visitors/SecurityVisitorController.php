<?php

namespace App\Http\Controllers\Api\Visitors;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\Visitors\ListVisitorsRequest;
use App\Http\Requests\Api\Visitors\StoreGateVisitorRequest;
use App\Http\Resources\Api\Visitors\VisitorResource;
use App\Models\Visitor;
use App\Services\Visitors\VisitorWorkflowService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Gate;

class SecurityVisitorController extends Controller
{
    public function __construct(private readonly VisitorWorkflowService $visitors) {}

    public function index(ListVisitorsRequest $request): AnonymousResourceCollection
    {
        Gate::authorize('viewAny', Visitor::class);

        return VisitorResource::collection($this->filteredVisitors($request)->latest()->paginate());
    }

    public function store(StoreGateVisitorRequest $request): VisitorResource
    {
        Gate::authorize('create', Visitor::class);
        $visitor = $this->visitors->createGateRequest($request->user(), $request->validated());

        return new VisitorResource($visitor->load('flat'));
    }

    public function show(Visitor $visitor): VisitorResource
    {
        Gate::authorize('view', $visitor);

        return new VisitorResource($visitor->load('flat'));
    }

    public function recordEntry(Visitor $visitor): VisitorResource
    {
        Gate::authorize('recordEntry', $visitor);

        return new VisitorResource($this->visitors->recordEntry($visitor));
    }

    public function recordExit(Visitor $visitor): VisitorResource
    {
        Gate::authorize('recordExit', $visitor);

        return new VisitorResource($this->visitors->recordExit($visitor));
    }

    private function filteredVisitors(ListVisitorsRequest $request): Builder
    {
        $filters = $request->validated();

        return Visitor::query()
            ->with('flat')
            ->search($filters['q'] ?? null)
            ->when($filters['approval_status'] ?? null, fn (Builder $query, string $status) => $query->where('approval_status', $status))
            ->when($filters['entry_status'] ?? null, fn (Builder $query, string $status) => $query->where('entry_status', $status))
            ->when($filters['visitor_type'] ?? null, fn (Builder $query, string $type) => $query->where('visitor_type', $type))
            ->when($filters['flat_id'] ?? null, fn (Builder $query, int $flatId) => $query->where('flat_id', $flatId));
    }
}
