<?php

namespace App\Http\Controllers\Api\Dashboard;

use App\Http\Controllers\Controller;
use App\Models\Complaint;
use App\Models\Flat;
use App\Models\MaintenanceBill;
use App\Models\MaintenancePayment;
use App\Models\Notice;
use App\Models\Parcel;
use App\Models\ParkingSlot;
use App\Models\Resident;
use App\Models\StaffMember;
use App\Models\Visitor;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AdminDashboardController extends Controller
{
    public function __invoke(Request $request): JsonResponse
    {
        abort_unless($request->user()->hasRole('ADMIN'), 403);

        $recentActivity = collect()
            ->merge(Resident::query()->with('user:id,name')->latest()->take(4)->get()->map(fn (Resident $resident) => [
                'type' => 'RESIDENT_REGISTERED',
                'title' => 'Resident registered',
                'detail' => $resident->user?->name ?? 'New resident',
                'occurred_at' => $resident->created_at?->toISOString(),
            ]))
            ->merge(MaintenancePayment::query()->where('status', MaintenancePayment::STATUS_COMPLETED)->latest('paid_at')->take(4)->get()->map(fn (MaintenancePayment $payment) => [
                'type' => 'PAYMENT_RECEIVED',
                'title' => 'Payment received',
                'detail' => '₹'.number_format((float) $payment->amount, 2),
                'occurred_at' => $payment->paid_at?->toISOString() ?? $payment->created_at?->toISOString(),
            ]))
            ->merge(Complaint::query()->latest()->take(4)->get()->map(fn (Complaint $complaint) => [
                'type' => 'COMPLAINT_CREATED',
                'title' => 'Complaint created',
                'detail' => $complaint->title,
                'occurred_at' => $complaint->created_at?->toISOString(),
            ]))
            ->merge(Visitor::query()->whereNotNull('entered_at')->latest('entered_at')->take(4)->get()->map(fn (Visitor $visitor) => [
                'type' => 'VISITOR_ENTRY',
                'title' => 'Visitor checked in',
                'detail' => $visitor->visitor_name,
                'occurred_at' => $visitor->entered_at?->toISOString(),
            ]))
            ->merge(MaintenanceBill::query()->latest()->take(4)->get()->map(fn (MaintenanceBill $bill) => [
                'type' => 'BILL_GENERATED',
                'title' => 'Maintenance bill generated',
                'detail' => 'Flat #'.$bill->flat_id,
                'occurred_at' => $bill->created_at?->toISOString(),
            ]))
            ->merge(Notice::query()->where('is_published', true)->latest('published_at')->take(4)->get()->map(fn (Notice $notice) => [
                'type' => 'NOTICE_PUBLISHED',
                'title' => 'Notice published',
                'detail' => $notice->title,
                'occurred_at' => $notice->published_at?->toISOString() ?? $notice->created_at?->toISOString(),
            ]))
            ->filter(fn (array $event) => $event['occurred_at'] !== null)
            ->sortByDesc('occurred_at')
            ->take(12)
            ->values();

        return response()->json(['data' => [
            'total_flats' => Flat::count(),
            'total_residents' => Resident::count(),
            'occupied_flats' => Flat::query()->where('occupancy_status', 'OCCUPIED')->count(),
            'vacant_flats' => Flat::query()->where('occupancy_status', 'VACANT')->count(),
            'today_visitors' => Visitor::query()->whereDate('created_at', today())->count(),
            'pending_visitor_approvals' => Visitor::query()->where('approval_status', Visitor::APPROVAL_PENDING)->count(),
            'open_complaints' => Complaint::query()->whereIn('status', [Complaint::STATUS_OPEN, Complaint::STATUS_IN_PROGRESS])->count(),
            'unpaid_maintenance_bills' => MaintenanceBill::query()->whereIn('status', [MaintenanceBill::STATUS_UNPAID, MaintenanceBill::STATUS_PARTIALLY_PAID, MaintenanceBill::STATUS_OVERDUE])->count(),
            'pending_maintenance_bills' => MaintenanceBill::query()->whereIn('status', [MaintenanceBill::STATUS_UNPAID, MaintenanceBill::STATUS_PARTIALLY_PAID, MaintenanceBill::STATUS_OVERDUE])->count(),
            'total_collected_maintenance' => round((float) MaintenancePayment::query()->where('status', MaintenancePayment::STATUS_COMPLETED)->sum('amount'), 2),
            'pending_payments' => MaintenancePayment::query()->where('status', MaintenancePayment::STATUS_PENDING)->count(),
            'available_parking_slots' => ParkingSlot::query()->where('status', ParkingSlot::STATUS_AVAILABLE)->count(),
            'active_staff' => StaffMember::query()->where('status', StaffMember::STATUS_ACTIVE)->count(),
            'parcels_pending_pickup' => Parcel::query()->whereIn('status', [Parcel::STATUS_RECEIVED, Parcel::STATUS_AWAITING_PICKUP])->count(),
            'recent_activity' => $recentActivity,
        ]]);
    }
}
