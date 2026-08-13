<?php

namespace App\Http\Controllers\Api\Maintenance;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\Maintenance\ListBillsRequest;
use App\Http\Resources\Api\Maintenance\MaintenanceBillResource;
use App\Models\MaintenanceBill;
use App\Models\MaintenancePayment;
use App\Models\Resident;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Gate;

class ResidentMaintenanceBillController extends Controller
{
    public function index(ListBillsRequest $request): AnonymousResourceCollection
    {
        Gate::authorize('viewAny', MaintenanceBill::class);
        $resident = $this->currentResident($request);
        $filters = $request->validated();

        $bills = MaintenanceBill::query()
            ->with('flat')
            ->withSum(['payments as paid_amount' => fn (Builder $query) => $query->where('status', MaintenancePayment::STATUS_COMPLETED)], 'amount')
            ->where('flat_id', $resident->flat_id)
            ->when($filters['status'] ?? null, fn (Builder $query, string $status) => $query->where('status', $status))
            ->when($filters['billing_month'] ?? null, fn (Builder $query, string $month) => $query->whereDate('billing_month', $month))
            ->latest('billing_month')
            ->paginate();

        $bills->getCollection()->transform(function (MaintenanceBill $bill): MaintenanceBill {
            $bill->outstanding_amount = max(0, (float) $bill->amount - (float) ($bill->paid_amount ?? 0));

            return $bill;
        });

        return MaintenanceBillResource::collection($bills);
    }

    public function show(Request $request, MaintenanceBill $bill): MaintenanceBillResource
    {
        Gate::authorize('view', $bill);
        $this->currentResident($request);

        $bill->load(['flat', 'payments']);
        $paid = $bill->payments->where('status', MaintenancePayment::STATUS_COMPLETED)->sum('amount');
        $bill->paid_amount = $paid;
        $bill->outstanding_amount = max(0, (float) $bill->amount - $paid);

        return new MaintenanceBillResource($bill);
    }

    private function currentResident(Request $request): Resident
    {
        return $request->user()->resident()->firstOrFail();
    }
}
