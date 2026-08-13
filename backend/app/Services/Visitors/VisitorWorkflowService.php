<?php

namespace App\Services\Visitors;

use App\Models\Resident;
use App\Models\User;
use App\Models\Visitor;
use App\Notifications\SocietyAlert;
use App\Notifications\VisitorAwaitingApproval;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class VisitorWorkflowService
{
    public function createGateRequest(User $securityUser, array $data): Visitor
    {
        return DB::transaction(function () use ($securityUser, $data): Visitor {
            $visitor = Visitor::create([
                ...Arr::only($data, ['visitor_name', 'mobile_number', 'purpose', 'flat_id', 'resident_id', 'vehicle_number', 'visitor_type']),
                'mobile_number' => $data['mobile_number'] ?? 'N/A',
                'purpose' => $data['purpose'] ?? 'Gate Visit',
                'entry_status' => Visitor::ENTRY_WAITING,
                'approval_status' => Visitor::APPROVAL_PENDING,
                'created_by_security_id' => $securityUser->id,
            ]);

            $visitor->load('resident.user');
            $visitor->resident->user->notify(new VisitorAwaitingApproval($visitor));

            return $visitor;
        });
    }

    public function createPreApproval(Resident $resident, array $data): Visitor
    {
        return Visitor::create([
            ...Arr::only($data, ['visitor_name', 'mobile_number', 'purpose', 'vehicle_number', 'visitor_type', 'expected_at']),
            'mobile_number' => $data['mobile_number'] ?? 'N/A',
            'purpose' => $data['purpose'] ?? 'Pre-approved Visit',
            'flat_id' => $resident->flat_id,
            'resident_id' => $resident->id,
            'entry_status' => Visitor::ENTRY_EXPECTED,
            'approval_status' => Visitor::APPROVAL_APPROVED,
            'approved_at' => now(),
            'approved_by_resident_id' => $resident->id,
            'is_pre_approved' => true,
        ]);
    }

    public function approve(Visitor $visitor, Resident $resident): Visitor
    {
        $this->ensurePending($visitor);

        $visitor->update([
            'approval_status' => Visitor::APPROVAL_APPROVED,
            'approved_at' => now(),
            'approved_by_resident_id' => $resident->id,
        ]);

        $visitor->createdBySecurity?->notify(new SocietyAlert(
            'visitor_approved',
            'Visitor approved',
            sprintf('%s was approved by the resident.', $visitor->visitor_name),
            ['visitor_id' => $visitor->id],
        ));

        return $visitor->fresh(['flat', 'resident']);
    }

    public function reject(Visitor $visitor, Resident $resident, ?string $reason): Visitor
    {
        $this->ensurePending($visitor);

        $visitor->update([
            'approval_status' => Visitor::APPROVAL_REJECTED,
            'approval_note' => $reason,
            'approved_at' => now(),
            'approved_by_resident_id' => $resident->id,
        ]);

        $visitor->createdBySecurity?->notify(new SocietyAlert(
            'visitor_rejected',
            'Visitor declined',
            sprintf('%s was declined by the resident.', $visitor->visitor_name),
            ['visitor_id' => $visitor->id],
        ));

        return $visitor->fresh(['flat', 'resident']);
    }

    public function recordEntry(Visitor $visitor): Visitor
    {
        if ($visitor->approval_status !== Visitor::APPROVAL_APPROVED || $visitor->entered_at) {
            throw ValidationException::withMessages(['visitor' => 'Only an approved visitor who has not entered can be recorded as entered.']);
        }

        $visitor->update([
            'entry_status' => Visitor::ENTRY_ENTERED,
            'entered_at' => now(),
        ]);

        $visitor->resident?->user?->notify(new SocietyAlert(
            'visitor_checked_in',
            'Visitor checked in',
            sprintf('%s has entered the society.', $visitor->visitor_name),
            ['visitor_id' => $visitor->id],
        ));

        return $visitor->fresh(['flat', 'resident']);
    }

    public function recordExit(Visitor $visitor): Visitor
    {
        if ($visitor->entry_status !== Visitor::ENTRY_ENTERED || $visitor->exited_at) {
            throw ValidationException::withMessages(['visitor' => 'Only a visitor currently inside can be recorded as exited.']);
        }

        $visitor->update([
            'entry_status' => Visitor::ENTRY_EXITED,
            'approval_status' => Visitor::APPROVAL_COMPLETED,
            'exited_at' => now(),
        ]);

        $visitor->resident?->user?->notify(new SocietyAlert(
            'visitor_checked_out',
            'Visitor checked out',
            sprintf('%s has exited the society.', $visitor->visitor_name),
            ['visitor_id' => $visitor->id],
        ));

        return $visitor->fresh(['flat', 'resident']);
    }

    private function ensurePending(Visitor $visitor): void
    {
        if ($visitor->approval_status !== Visitor::APPROVAL_PENDING) {
            throw ValidationException::withMessages(['visitor' => 'Only a pending visitor request can be actioned.']);
        }
    }
}
