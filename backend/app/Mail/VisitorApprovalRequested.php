<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class VisitorApprovalRequested extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public readonly string $visitorName,
        public readonly string $visitorType,
        public readonly string $flatNumber,
        public readonly string $building,
        public readonly string $requestStatus,
        public readonly string $requestedAt,
        public readonly string $residentName,
        public readonly ?string $approveUrl = null,
        public readonly ?string $rejectUrl = null,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Smart Society — Visitor Approval Request: ' . $this->visitorName,
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.visitors.approval_requested',
            text: 'emails.visitors.approval_requested_plain',
            with: [
                'visitorName' => $this->visitorName,
                'visitorType' => $this->visitorType,
                'flatNumber' => $this->flatNumber,
                'building' => $this->building,
                'requestStatus' => $this->requestStatus,
                'requestedAt' => $this->requestedAt,
                'residentName' => $this->residentName,
                'approveUrl' => $this->approveUrl,
                'rejectUrl' => $this->rejectUrl,
            ],
        );
    }

    public function attachments(): array
    {
        return [];
    }
}