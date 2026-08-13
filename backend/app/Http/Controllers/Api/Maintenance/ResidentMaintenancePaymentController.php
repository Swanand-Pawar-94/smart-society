<?php

namespace App\Http\Controllers\Api\Maintenance;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\Maintenance\ListPaymentsRequest;
use App\Http\Requests\Api\Maintenance\ResidentSubmitPaymentRequest;
use App\Http\Resources\Api\Maintenance\MaintenancePaymentResource;
use App\Models\MaintenanceBill;
use App\Models\MaintenancePayment;
use App\Models\Resident;
use App\Services\Maintenance\MaintenanceService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Gate;

class ResidentMaintenancePaymentController extends Controller
{
    public function __construct(private readonly MaintenanceService $maintenance) {}

    public function index(ListPaymentsRequest $request): AnonymousResourceCollection
    {
        Gate::authorize('viewAny', MaintenancePayment::class);
        $resident = $this->currentResident($request);
        $filters = $request->validated();

        $payments = MaintenancePayment::query()
            ->with(['bill.flat'])
            ->where('resident_id', $resident->id)
            ->when($filters['status'] ?? null, fn (Builder $query, string $status) => $query->where('status', $status))
            ->when($filters['maintenance_bill_id'] ?? null, fn (Builder $query, int $billId) => $query->where('maintenance_bill_id', $billId))
            ->latest()
            ->paginate();

        return MaintenancePaymentResource::collection($payments);
    }

    public function store(ResidentSubmitPaymentRequest $request): MaintenancePaymentResource
    {
        Gate::authorize('create', MaintenancePayment::class);
        $resident = $this->currentResident($request);
        $data = $request->validated();

        Gate::authorize('view', MaintenanceBill::findOrFail($data['maintenance_bill_id']));

        return new MaintenancePaymentResource(
            $this->maintenance->initiateResidentPayment($resident, $data),
        );
    }

    public function show(Request $request, MaintenancePayment $payment): MaintenancePaymentResource
    {
        Gate::authorize('view', $payment);
        $this->currentResident($request);

        return new MaintenancePaymentResource($payment->load(['bill.flat']));
    }

    private function currentResident(Request $request): Resident
    {
        return $request->user()->resident()->firstOrFail();
    }
}
