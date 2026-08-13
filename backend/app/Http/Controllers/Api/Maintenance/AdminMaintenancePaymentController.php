<?php

namespace App\Http\Controllers\Api\Maintenance;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\Maintenance\ListPaymentsRequest;
use App\Http\Requests\Api\Maintenance\StorePaymentRequest;
use App\Http\Requests\Api\Maintenance\TransitionPaymentRequest;
use App\Http\Resources\Api\Maintenance\MaintenancePaymentResource;
use App\Models\MaintenancePayment;
use App\Services\Maintenance\MaintenanceService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Gate;

class AdminMaintenancePaymentController extends Controller
{
    public function __construct(private readonly MaintenanceService $maintenance) {}

    public function index(ListPaymentsRequest $request): AnonymousResourceCollection
    {
        Gate::authorize('viewAny', MaintenancePayment::class);
        $filters = $request->validated();

        $payments = MaintenancePayment::query()
            ->with(['bill.flat', 'resident'])
            ->when($filters['status'] ?? null, fn (Builder $query, string $status) => $query->where('status', $status))
            ->when($filters['maintenance_bill_id'] ?? null, fn (Builder $query, int $billId) => $query->where('maintenance_bill_id', $billId))
            ->when($filters['flat_id'] ?? null, fn (Builder $query, int $flatId) => $query->whereHas('bill', fn (Builder $billQuery) => $billQuery->where('flat_id', $flatId)))
            ->latest()
            ->paginate();

        return MaintenancePaymentResource::collection($payments);
    }

    public function store(StorePaymentRequest $request): MaintenancePaymentResource
    {
        Gate::authorize('create', MaintenancePayment::class);

        return new MaintenancePaymentResource($this->maintenance->recordPayment($request->validated()));
    }

    public function show(MaintenancePayment $payment): MaintenancePaymentResource
    {
        Gate::authorize('view', $payment);

        return new MaintenancePaymentResource($payment->load(['bill.flat', 'resident']));
    }

    public function transition(TransitionPaymentRequest $request, MaintenancePayment $payment): MaintenancePaymentResource
    {
        Gate::authorize('transition', $payment);

        return new MaintenancePaymentResource(
            $this->maintenance->transition(
                $payment,
                $request->validated('status'),
                $request->validated('receipt_reference'),
            ),
        );
    }
}
