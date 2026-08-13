<?php

namespace App\Http\Controllers\Api\Dashboard;

use App\Http\Controllers\Controller;
use App\Models\AmenityBooking;
use App\Models\Complaint;
use App\Models\MaintenanceBill;
use App\Models\MaintenancePayment;
use App\Models\Notice;
use App\Models\Visitor;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ResidentDashboardController extends Controller
{
    public function __invoke(Request $request): JsonResponse
    {
        $resident = $request->user()->resident()
            ->with(['user:id,name,email,phone,role', 'flat.owner:id,name,email'])
            ->firstOrFail();

        $bills = MaintenanceBill::query()
            ->where('flat_id', $resident->flat_id)
            ->withSum([
                'payments as paid_amount' => fn (Builder $query) => $query
                    ->where('status', MaintenancePayment::STATUS_COMPLETED),
            ], 'amount')
            ->orderBy('due_date')
            ->get();

        $openBills = $bills->map(function (MaintenanceBill $bill): MaintenanceBill {
            $bill->outstanding_amount = max(0, (float) $bill->amount - (float) ($bill->paid_amount ?? 0));

            return $bill;
        })->filter(fn (MaintenanceBill $bill) => $bill->outstanding_amount > 0)->values();

        $today = today();
        $upcomingBooking = AmenityBooking::query()
            ->with('amenity:id,name')
            ->where('resident_id', $resident->id)
            ->where('status', AmenityBooking::STATUS_CONFIRMED)
            ->whereDate('booking_date', '>=', $today)
            ->orderBy('booking_date')
            ->orderBy('start_time')
            ->first();

        $latestNotice = Notice::query()
            ->published()
            ->forAudience(Notice::AUDIENCE_RESIDENTS)
            ->latest('published_at')
            ->first(['id', 'title', 'content', 'audience', 'published_at']);

        return response()->json(['data' => [
            'resident' => [
                'name' => $resident->user->name,
                'email' => $resident->user->email,
                'phone' => $resident->user->phone,
                'relation_to_owner' => $resident->relation_to_owner,
                'flat_number' => $resident->flat->flat_number,
                'building' => $resident->flat->building,
            ],
            'dues' => [
                'outstanding_amount' => round($openBills->sum('outstanding_amount'), 2),
                'overdue_amount' => round($openBills
                    ->filter(fn (MaintenanceBill $bill) => $bill->due_date->lt($today))
                    ->sum('outstanding_amount'), 2),
                'next_bill' => $openBills->first(fn (MaintenanceBill $bill) => $bill->due_date->gte($today))
                    ?? $openBills->first(),
            ],
            'visitors' => [
                'expected_today' => Visitor::query()
                    ->where('flat_id', $resident->flat_id)
                    ->whereDate('expected_at', $today)
                    ->whereIn('approval_status', [Visitor::APPROVAL_PENDING, Visitor::APPROVAL_APPROVED])
                    ->count(),
                'at_gate' => Visitor::query()
                    ->where('flat_id', $resident->flat_id)
                    ->where('entry_status', Visitor::ENTRY_WAITING)
                    ->where('approval_status', Visitor::APPROVAL_PENDING)
                    ->count(),
            ],
            'complaints' => [
                'open' => Complaint::query()
                    ->where('resident_id', $resident->id)
                    ->whereIn('status', [Complaint::STATUS_OPEN, Complaint::STATUS_IN_PROGRESS])
                    ->count(),
            ],
            'upcoming_booking' => $upcomingBooking ? [
                'id' => $upcomingBooking->id,
                'amenity_name' => $upcomingBooking->amenity->name,
                'booking_date' => $upcomingBooking->booking_date->toDateString(),
                'start_time' => $upcomingBooking->start_time?->format('H:i'),
            ] : null,
            'latest_notice' => $latestNotice,
            'unread_notifications' => $request->user()->unreadNotifications()->count(),
        ]]);
    }
}
