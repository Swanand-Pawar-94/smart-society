<?php

namespace App\Http\Controllers\Api\Maintenance;

use App\Http\Controllers\Controller;
use App\Http\Resources\Api\Maintenance\MaintenanceBillResource;
use App\Models\MaintenanceBill;
use App\Models\MaintenancePayment;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ResidentMaintenanceController extends Controller
{
    public function history(Request $request): JsonResponse
    {
        $resident = $request->user()->resident;
        abort_unless($resident, 403);

        $bills = MaintenanceBill::query()
            ->with(['payments' => fn ($q) => $q->latest()])
            ->where('flat_id', $resident->flat_id)
            ->latest('billing_month')
            ->paginate();

        $bills->getCollection()->transform(function (MaintenanceBill $bill): MaintenanceBill {
            $paid = $bill->payments->where('status', MaintenancePayment::STATUS_COMPLETED)->sum('amount');
            $bill->paid_amount = $paid;
            $bill->outstanding_amount = max(0, (float) $bill->amount - $paid);

            return $bill;
        });

        return MaintenanceBillResource::collection($bills)->response();
    }

    public function payments(Request $request): JsonResponse
    {
        $resident = $request->user()->resident;
        abort_unless($resident, 403);

        $payments = MaintenancePayment::query()
            ->with('bill')
            ->where('resident_id', $resident->id)
            ->latest('created_at')
            ->paginate();

        return response()->json(['data' => $payments]);
    }
}
