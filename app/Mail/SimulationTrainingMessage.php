<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

final class SimulationTrainingMessage extends Mailable
{
    use Queueable;

    public function __construct(public readonly string $trainingUrl) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: 'A short workplace security learning note');
    }

    public function content(): Content
    {
        return new Content(view: 'emails.simulation-training', with: ['trainingUrl' => $this->trainingUrl]);
    }
}
