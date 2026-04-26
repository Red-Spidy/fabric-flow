<?php

namespace App\Mail;

use App\Models\FabricBatch;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class ShipmentDelayedEmail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public FabricBatch $batch,
        public float $hoursOverdue
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: "Update on your FabricBatch ({$this->batch->batch_id})",
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.shipment-delayed',
        );
    }

    public function attachments(): array
    {
        return [];
    }
}
