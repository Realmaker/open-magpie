<?php

declare(strict_types=1);

namespace App\Mail;

use App\Models\Project;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class ProjectShareInvitation extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public Project $project,
        public User $sharedBy,
        public string $permission,
        public string $acceptUrl,
        public string $recipientEmail,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: "{$this->sharedBy->name} hat ein Projekt mit dir geteilt: {$this->project->name}",
        );
    }

    public function content(): Content
    {
        return new Content(
            markdown: 'mail.project-share-invitation',
        );
    }
}
