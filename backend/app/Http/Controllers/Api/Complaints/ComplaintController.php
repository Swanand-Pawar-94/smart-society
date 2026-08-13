<?php

namespace App\Http\Controllers\Api\Complaints;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\Complaints\StoreComplaintRequest;
use App\Http\Requests\Api\Complaints\UpdateComplaintRequest;
use App\Http\Resources\Api\Complaints\ComplaintResource;
use App\Models\Complaint;
use App\Services\Complaints\ComplaintService;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Gate;

class ComplaintController extends Controller
{
    public function __construct(private readonly ComplaintService $complaints) {}

    public function index(Request $request): AnonymousResourceCollection
    {
        Gate::authorize('viewAny', Complaint::class);
        $user = $request->user();
        $query = Complaint::query()->with('assignedStaff')->latest();
        if ($user->hasRole('RESIDENT')) {
            $query->where('resident_id', $user->resident?->id);
        }
        if ($user->hasRole('STAFF')) {
            $query->where('assigned_staff_id', $user->staffMember?->id);
        }

        return ComplaintResource::collection($query->paginate());
    }

    public function store(StoreComplaintRequest $request): ComplaintResource
    {
        Gate::authorize('create', Complaint::class);

        return new ComplaintResource($this->complaints->create($request->user()->resident, $request->validated()));
    }

    public function show(Complaint $complaint): ComplaintResource
    {
        Gate::authorize('view', $complaint);

        return new ComplaintResource($complaint->load(['assignedStaff', 'statusHistories.changedBy']));
    }

    public function update(UpdateComplaintRequest $request, Complaint $complaint): ComplaintResource
    {
        Gate::authorize('update', $complaint);

        return new ComplaintResource($this->complaints->update($complaint, $request->user(), $request->validated()));
    }
}
