<?php

namespace App\Http\Controllers\Api\Dashboard;

use App\Http\Controllers\Controller;
use App\Models\Complaint;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class StaffDashboardController extends Controller
{
    public function __invoke(Request $request): JsonResponse
    {
        $staff = $request->user()->staffMember;

        if (! $staff) {
            return response()->json(['data' => [
                'staff_member' => null,
                'assigned_tasks' => 0,
                'pending_tasks' => 0,
                'completed_tasks' => 0,
                'recent_complaints' => [],
            ]]);
        }

        $complaints = Complaint::query()->where('assigned_staff_id', $staff->id);

        return response()->json(['data' => [
            'staff_member' => [
                'designation' => $staff->designation,
                'shift' => $staff->shift,
                'status' => $staff->status,
                'joining_date' => $staff->joining_date?->toDateString(),
            ],
            'assigned_tasks' => (clone $complaints)->count(),
            'pending_tasks' => (clone $complaints)->whereIn('status', [Complaint::STATUS_OPEN, Complaint::STATUS_IN_PROGRESS])->count(),
            'completed_tasks' => (clone $complaints)->whereIn('status', [Complaint::STATUS_RESOLVED, Complaint::STATUS_CLOSED])->count(),
            'recent_complaints' => (clone $complaints)->with('flat:id,flat_number,building')->latest()->take(8)->get()->map(fn (Complaint $complaint) => [
                'id' => $complaint->id,
                'title' => $complaint->title,
                'priority' => $complaint->priority,
                'status' => $complaint->status,
                'flat' => $complaint->flat ? $complaint->flat->building.' '.$complaint->flat->flat_number : null,
                'created_at' => $complaint->created_at?->toISOString(),
            ]),
        ]]);
    }
}
