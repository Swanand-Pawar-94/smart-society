<?php

namespace App\Notifications;

use App\Models\Visitor;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class VisitorAwaitingApproval extends Notification
{
    use Queueable;

    public function __construct(private readonly Visitor $visitor) {}

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toArray(object $notifiable): array
    {
        return [
            'type' => 'visitor_waiting_for_approval',
            'visitor_id' => $this->visitor->id,
            'visitor_name' => $this->visitor->visitor_name,
            'flat_id' => $this->visitor->flat_id,
            'purpose' => $this->visitor->purpose,
        ];
    }
}
