<?php

namespace App\Http\Controllers\Api\Visitors;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\Visitors\ListVisitorsRequest;
use App\Http\Requests\Api\Visitors\RejectVisitorRequest;
use App\Http\Requests\Api\Visitors\StorePreApprovedVisitorRequest;
use App\Http\Resources\Api\Visitors\VisitorResource;
use App\Models\Resident;
use App\Models\Visitor;
use App\Services\Visitors\VisitorWorkflowService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Gate;

class ResidentVisitorController extends Controller
{
    public function __construct(private readonly VisitorWorkflowService $visitors) {}

    public function index(ListVisitorsRequest $request): AnonymousResourceCollection
    {
        return VisitorResource::collection($this->residentVisitors($request)->latest()->paginate());
    }

    public function pending(ListVisitorsRequest $request): AnonymousResourceCollection
    {
        return VisitorResource::collection(
            $this->residentVisitors($request)
                ->where('approval_status', Visitor::APPROVAL_PENDING)
                ->latest()
                ->paginate(),
        );
    }

    public function show(Request $request, Visitor $visitor): VisitorResource
    {
        Gate::authorize('view', $visitor);
        $this->currentResident($request);

        return new VisitorResource($visitor->load('flat'));
    }

    public function approve(Request $request, Visitor $visitor): VisitorResource
    {
        Gate::authorize('approve', $visitor);

        return new VisitorResource($this->visitors->approve($visitor, $this->currentResident($request)));
    }

    public function reject(RejectVisitorRequest $request, Visitor $visitor): VisitorResource
    {
        Gate::authorize('reject', $visitor);

        return new VisitorResource($this->visitors->reject($visitor, $this->currentResident($request), $request->validated('reason')));
    }

    public function preApprove(StorePreApprovedVisitorRequest $request): VisitorResource
    {
        $resident = $this->currentResident($request);
        $visitor = $this->visitors->createPreApproval($resident, $request->validated());

        return new VisitorResource($visitor->load('flat'));
    }

    private function residentVisitors(ListVisitorsRequest $request): Builder
    {
        $resident = $this->currentResident($request);
        $filters = $request->validated();

        return Visitor::query()
            ->with('flat')
            ->where('flat_id', $resident->flat_id)
            ->search($filters['q'] ?? null)
            ->when($filters['approval_status'] ?? null, fn (Builder $query, string $status) => $query->where('approval_status', $status))
            ->when($filters['entry_status'] ?? null, fn (Builder $query, string $status) => $query->where('entry_status', $status))
            ->when($filters['visitor_type'] ?? null, fn (Builder $query, string $type) => $query->where('visitor_type', $type));
    }

    private function currentResident(Request $request): Resident
    {
        return $request->user()->resident()->firstOrFail();
    }
}
