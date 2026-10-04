<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\Visitor;
use App\Notifications\SocietyAlert;
use Illuminate\Http\Request;
use Illuminate\View\View;

class VisitorWebActionController extends Controller
{
    public function handle(Request $request, Visitor $visitor, string $action): View
    {
        if (! $request->hasValidSignature()) {
            return view('emails.visitors.action_confirmed', [
                'status' => 'INVALID_SIGNATURE',
                'title' => 'Invalid or Expired Link',
                'message' => 'This authorization link is invalid or has expired. Please use the Smart Society mobile app to respond.',
                'visitor' => $visitor,
            ]);
        }

        if ($visitor->approval_status !== Visitor::APPROVAL_PENDING) {
            $isApproved = $visitor->approval_status === Visitor::APPROVAL_APPROVED;
            return view('emails.visitors.action_confirmed', [
                'status' => 'ALREADY_ACTIONED',
                'title' => 'Request Already Actioned',
                'message' => "This visitor request has already been marked as {$visitor->approval_status}.",
                'visitor' => $visitor,
                'isApproved' => $isApproved,
            ]);
        }

        $resident = $visitor->resident;

        if ($action === 'approve') {
            $visitor->update([
                'approval_status' => Visitor::APPROVAL_APPROVED,
                'approved_at' => now(),
                'approved_by_resident_id' => $resident?->id,
            ]);

            $visitor->createdBySecurity?->notify(new SocietyAlert(
                'visitor_approved',
                'Visitor approved',
                sprintf('%s was approved by the resident via email.', $visitor->visitor_name),
                ['visitor_id' => $visitor->id],
            ));

            return view('emails.visitors.action_confirmed', [
                'status' => 'APPROVED',
                'title' => 'Visitor Approved',
                'message' => "{$visitor->visitor_name} has been approved for entry to Flat {$visitor->flat?->flat_number} ({$visitor->flat?->building}). Security gate has been notified.",
                'visitor' => $visitor,
                'isApproved' => true,
            ]);
        }

        if ($action === 'reject') {
            $visitor->update([
                'approval_status' => Visitor::APPROVAL_REJECTED,
                'approval_note' => 'Declined via email link',
                'approved_at' => now(),
                'approved_by_resident_id' => $resident?->id,
            ]);

            $visitor->createdBySecurity?->notify(new SocietyAlert(
                'visitor_rejected',
                'Visitor declined',
                sprintf('%s was declined by the resident via email.', $visitor->visitor_name),
                ['visitor_id' => $visitor->id],
            ));

            return view('emails.visitors.action_confirmed', [
                'status' => 'REJECTED',
                'title' => 'Visitor Declined',
                'message' => "Entry for {$visitor->visitor_name} has been declined. Security gate has been notified.",
                'visitor' => $visitor,
                'isApproved' => false,
            ]);
        }

        return view('emails.visitors.action_confirmed', [
            'status' => 'UNKNOWN_ACTION',
            'title' => 'Unknown Action',
            'message' => 'Invalid action requested.',
            'visitor' => $visitor,
        ]);
    }
}