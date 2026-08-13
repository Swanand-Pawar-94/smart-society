<?php

namespace App\Http\Controllers\Api\Dashboard;

use App\Http\Controllers\Controller;
use App\Models\Parcel;
use App\Models\Visitor;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SecurityDashboardController extends Controller
{
    public function __invoke(Request $request): JsonResponse
    {
        $today = today();

        return response()->json(['data' => [
            'expected_visitors' => Visitor::query()
                ->whereDate('expected_at', $today)
                ->whereIn('approval_status', [Visitor::APPROVAL_PENDING, Visitor::APPROVAL_APPROVED])
                ->count(),
            'waiting_for_approval' => Visitor::query()
                ->where('entry_status', Visitor::ENTRY_WAITING)
                ->where('approval_status', Visitor::APPROVAL_PENDING)
                ->count(),
            'checked_in_today' => Visitor::query()->whereDate('entered_at', $today)->count(),
            'checked_out_today' => Visitor::query()->whereDate('exited_at', $today)->count(),
            'parcels_awaiting_pickup' => Parcel::query()
                ->whereIn('status', [Parcel::STATUS_RECEIVED, Parcel::STATUS_AWAITING_PICKUP])
                ->count(),
        ]]);
    }
}
