<?php

namespace App\Http\Controllers\Api\Reports;

use App\Http\Controllers\Controller;
use App\Models\Complaint;
use App\Models\Flat;
use App\Models\MaintenanceBill;
use App\Models\MaintenancePayment;
use App\Models\ParkingSlot;
use App\Models\StaffMember;
use App\Models\Visitor;
use Carbon\CarbonImmutable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AdminReportController extends Controller
{
    public function __invoke(Request $request): JsonResponse
    {
        abort_unless($request->user()->hasRole('ADMIN'), 403);
        $data = $request->validate([
            'range' => ['nullable', 'in:TODAY,THIS_WEEK,THIS_MONTH,LAST_MONTH,THIS_YEAR,CUSTOM'],
            'date_from' => ['nullable', 'required_if:range,CUSTOM', 'date'],
            'date_to' => ['nullable', 'required_if:range,CUSTOM', 'date', 'after_or_equal:date_from'],
        ]);
        [$from, $to, $label] = $this->dateRange($data);

        $completedPayments = MaintenancePayment::query()
            ->where('status', MaintenancePayment::STATUS_COMPLETED)
            ->whereBetween('paid_at', [$from, $to]);
        $bills = MaintenanceBill::query()->whereBetween('created_at', [$from, $to]);
        $complaints = Complaint::query()->whereBetween('created_at', [$from, $to]);
        $visitors = Visitor::query()->whereBetween('created_at', [$from, $to]);

        return response()->json(['data' => [
            'range' => [
                'label' => $label,
                'date_from' => $from->toDateString(),
                'date_to' => $to->toDateString(),
            ],
            'maintenance' => [
                'collection' => round((float) (clone $completedPayments)->sum('amount'), 2),
                'payments_received' => (clone $completedPayments)->count(),
                'bills_generated' => (clone $bills)->count(),
                'billed_amount' => round((float) (clone $bills)->sum('amount'), 2),
                'pending_amount' => round((float) MaintenanceBill::query()
                    ->whereIn('status', [
                        MaintenanceBill::STATUS_UNPAID,
                        MaintenanceBill::STATUS_PARTIALLY_PAID,
                        MaintenanceBill::STATUS_OVERDUE,
                    ])->sum('amount'), 2),
            ],
            'complaints' => [
                'created' => (clone $complaints)->count(),
                'open' => (clone $complaints)->whereIn('status', [
                    Complaint::STATUS_OPEN,
                    Complaint::STATUS_ASSIGNED,
                    Complaint::STATUS_IN_PROGRESS,
                    Complaint::STATUS_REOPENED,
                ])->count(),
                'resolved' => (clone $complaints)->whereIn('status', [
                    Complaint::STATUS_RESOLVED,
                    Complaint::STATUS_CLOSED,
                ])->count(),
            ],
            'visitors' => [
                'registered' => (clone $visitors)->count(),
                'entered' => (clone $visitors)->whereNotNull('entered_at')->count(),
                'exited' => (clone $visitors)->whereNotNull('exited_at')->count(),
            ],
            'occupancy' => [
                'total_flats' => Flat::count(),
                'occupied_flats' => Flat::query()->where('occupancy_status', 'OCCUPIED')->count(),
                'vacant_flats' => Flat::query()->where('occupancy_status', 'VACANT')->count(),
            ],
            'parking' => [
                'total_slots' => ParkingSlot::count(),
                'assigned_slots' => ParkingSlot::query()->where('status', ParkingSlot::STATUS_ASSIGNED)->count(),
                'available_slots' => ParkingSlot::query()->where('status', ParkingSlot::STATUS_AVAILABLE)->count(),
            ],
            'staff' => [
                'active' => StaffMember::query()->where('status', StaffMember::STATUS_ACTIVE)->count(),
                'on_leave' => StaffMember::query()->where('status', StaffMember::STATUS_ON_LEAVE)->count(),
            ],
            'collection_by_day' => (clone $completedPayments)
                ->selectRaw('DATE(paid_at) as date, SUM(amount) as amount')
                ->groupByRaw('DATE(paid_at)')
                ->orderByRaw('DATE(paid_at)')
                ->get()
                ->map(fn ($row) => ['date' => $row->date, 'amount' => round((float) $row->amount, 2)]),
        ]]);
    }

    private function dateRange(array $data): array
    {
        $today = CarbonImmutable::today();
        $range = $data['range'] ?? 'THIS_MONTH';

        [$from, $to, $label] = match ($range) {
            'TODAY' => [$today, $today, 'Today'],
            'THIS_WEEK' => [$today->startOfWeek(), $today->endOfWeek(), 'This week'],
            'LAST_MONTH' => [$today->subMonthNoOverflow()->startOfMonth(), $today->subMonthNoOverflow()->endOfMonth(), 'Last month'],
            'THIS_YEAR' => [$today->startOfYear(), $today->endOfYear(), 'This year'],
            'CUSTOM' => [
                CarbonImmutable::parse($data['date_from'])->startOfDay(),
                CarbonImmutable::parse($data['date_to'])->endOfDay(),
                'Custom range',
            ],
            default => [$today->startOfMonth(), $today->endOfMonth(), 'This month'],
        };

        return [$from->startOfDay(), $to->endOfDay(), $label];
    }
}
