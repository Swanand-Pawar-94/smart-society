<?php

namespace App\Http\Controllers\Api\Maintenance;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\Maintenance\ListBillsRequest;
use App\Http\Requests\Api\Maintenance\StoreBillRequest;
use App\Http\Requests\Api\Maintenance\UpdateBillRequest;
use App\Http\Resources\Api\Maintenance\MaintenanceBillResource;
use App\Models\MaintenanceBill;
use App\Models\MaintenancePayment;
use App\Services\Maintenance\MaintenanceService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Gate;

class AdminMaintenanceBillController extends Controller
{
    public function __construct(private readonly MaintenanceService $maintenance) {}

    public function index(ListBillsRequest $request): AnonymousResourceCollection
    {
        Gate::authorize('viewAny', MaintenanceBill::class);
        $this->maintenance->syncOverdueBills();
        $filters = $request->validated();

        $bills = MaintenanceBill::query()
            ->with('flat')
            ->withSum(['payments as paid_amount' => fn (Builder $query) => $query->where('status', MaintenancePayment::STATUS_COMPLETED)], 'amount')
            ->when($filters['status'] ?? null, fn (Builder $query, string $status) => $query->where('status', $status))
            ->when($filters['flat_id'] ?? null, fn (Builder $query, int $flatId) => $query->where('flat_id', $flatId))
            ->when($filters['billing_month'] ?? null, fn (Builder $query, string $month) => $query->whereDate('billing_month', $month))
            ->latest('billing_month')
            ->paginate();

        $bills->getCollection()->transform(function (MaintenanceBill $bill): MaintenanceBill {
            $bill->outstanding_amount = max(0, (float) $bill->amount - (float) ($bill->paid_amount ?? 0));

            return $bill;
        });

        return MaintenanceBillResource::collection($bills);
    }

    public function store(StoreBillRequest $request): MaintenanceBillResource
    {
        Gate::authorize('create', MaintenanceBill::class);

        return new MaintenanceBillResource($this->maintenance->createBill($request->validated()));
    }

    public function show(MaintenanceBill $bill): MaintenanceBillResource
    {
        Gate::authorize('view', $bill);

        $bill->load(['flat', 'payments.resident']);
        $paid = $bill->payments->where('status', MaintenancePayment::STATUS_COMPLETED)->sum('amount');
        $bill->paid_amount = $paid;
        $bill->outstanding_amount = max(0, (float) $bill->amount - $paid);

        return new MaintenanceBillResource($bill);
    }

    public function update(UpdateBillRequest $request, MaintenanceBill $bill): MaintenanceBillResource
    {
        Gate::authorize('update', $bill);

        return new MaintenanceBillResource($this->maintenance->updateBill($bill, $request->validated()));
    }

    public function destroy(MaintenanceBill $bill): Response
    {
        Gate::authorize('delete', $bill);
        $bill->delete();

        return response()->noContent();
    }

    public function collection(): JsonResponse
    {
        Gate::authorize('manage', MaintenanceBill::class);

        return response()->json(['data' => $this->maintenance->collectionSummary()]);
    }
}
