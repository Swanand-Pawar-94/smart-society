<?php

namespace App\Services\Complaints;

use App\Models\Complaint;
use App\Models\ComplaintStatusHistory;
use App\Models\Resident;
use App\Models\User;
use App\Notifications\SocietyAlert;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ComplaintService
{
    public function create(Resident $resident, array $data): Complaint
    {
        return DB::transaction(function () use ($resident, $data): Complaint {
            $complaint = Complaint::create([
                'resident_id' => $resident->id,
                'flat_id' => $resident->flat_id,
                'category' => $data['category'],
                'title' => $data['title'],
                'description' => $data['description'],
                'priority' => $data['priority'] ?? Complaint::PRIORITY_MEDIUM,
                'status' => Complaint::STATUS_OPEN,
            ]);

            $this->recordHistory($complaint, null, Complaint::STATUS_OPEN, $resident->user_id);

            User::query()->where('role', User::ROLE_ADMIN)->each(function (User $admin) use ($complaint): void {
                $admin->notify(new SocietyAlert(
                    'complaint_created',
                    'New complaint',
                    $complaint->title,
                    ['complaint_id' => $complaint->id, 'priority' => $complaint->priority],
                ));
            });

            return $complaint->fresh(['flat', 'assignedStaff']);
        });
    }

    public function update(Complaint $complaint, User $user, array $data): Complaint
    {
        return DB::transaction(function () use ($complaint, $user, $data): Complaint {
            $previousStatus = $complaint->status;
            $updates = Arr::only($data, ['category', 'title', 'description', 'priority', 'assigned_staff_id']);

            if (array_key_exists('assigned_staff_id', $data)
                && $data['assigned_staff_id'] !== null
                && ! isset($data['status'])
                && in_array($complaint->status, [Complaint::STATUS_OPEN, Complaint::STATUS_REOPENED], true)) {
                $updates['status'] = Complaint::STATUS_ASSIGNED;
            }

            if (isset($data['status'])) {
                $this->ensureValidTransition($complaint->status, $data['status']);
                $updates['status'] = $data['status'];

                if ($data['status'] === Complaint::STATUS_RESOLVED) {
                    $updates['resolved_at'] = now();
                }

                if ($data['status'] === Complaint::STATUS_CLOSED) {
                    $updates['closed_at'] = now();
                }
            }

            $complaint->update($updates);

            $nextStatus = $updates['status'] ?? null;
            if ($nextStatus !== null && $nextStatus !== $previousStatus) {
                $this->recordHistory(
                    $complaint,
                    $previousStatus,
                    $nextStatus,
                    $user->id,
                    $data['note'] ?? null,
                );
            }

            if ($nextStatus !== null && $nextStatus !== $previousStatus) {
                $complaint->loadMissing('resident.user');
                $complaint->resident?->user?->notify(new SocietyAlert(
                    'complaint_status_changed',
                    'Complaint updated',
                    sprintf('%s is now %s.', $complaint->title, strtolower(str_replace('_', ' ', $nextStatus))),
                    ['complaint_id' => $complaint->id, 'status' => $nextStatus],
                ));
            }

            if (array_key_exists('assigned_staff_id', $updates) && $complaint->assignedStaff?->user) {
                $complaint->assignedStaff->user->notify(new SocietyAlert(
                    'complaint_assigned',
                    'Complaint assigned to you',
                    $complaint->title,
                    ['complaint_id' => $complaint->id],
                ));
            }

            return $complaint->fresh(['flat', 'assignedStaff', 'statusHistories.changedBy']);
        });
    }

    private function ensureValidTransition(string $from, string $to): void
    {
        $allowed = [
            Complaint::STATUS_OPEN => [Complaint::STATUS_ASSIGNED, Complaint::STATUS_IN_PROGRESS, Complaint::STATUS_CLOSED],
            Complaint::STATUS_ASSIGNED => [Complaint::STATUS_IN_PROGRESS, Complaint::STATUS_CLOSED],
            Complaint::STATUS_IN_PROGRESS => [Complaint::STATUS_RESOLVED, Complaint::STATUS_CLOSED],
            Complaint::STATUS_RESOLVED => [Complaint::STATUS_CLOSED, Complaint::STATUS_REOPENED],
            Complaint::STATUS_CLOSED => [Complaint::STATUS_REOPENED],
            Complaint::STATUS_REOPENED => [Complaint::STATUS_ASSIGNED, Complaint::STATUS_IN_PROGRESS, Complaint::STATUS_CLOSED],
        ];

        if (! in_array($to, $allowed[$from] ?? [], true)) {
            throw ValidationException::withMessages([
                'status' => 'Invalid complaint status transition.',
            ]);
        }
    }

    private function recordHistory(
        Complaint $complaint,
        ?string $from,
        string $to,
        int $userId,
        ?string $note = null,
    ): void {
        ComplaintStatusHistory::create([
            'complaint_id' => $complaint->id,
            'from_status' => $from,
            'to_status' => $to,
            'changed_by_user_id' => $userId,
            'note' => $note,
        ]);
    }
}
