<?php

declare(strict_types=1);

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * E-mail of a notification. It is queued and sent only after the transaction that generated it commits.
 */
class AppNotificationMail extends Mailable implements ShouldQueue
{
    use Queueable;
    use SerializesModels;

    public function __construct(public string $message, public string $url)
    {
        $this->afterCommit();
    }

    public function envelope(): Envelope
    {
        return new Envelope(subject: config('app.name').': '.$this->message);
    }

    public function content(): Content
    {
        return new Content(htmlString: '<p>'.e($this->message).'</p><p><a href="'.e($this->url).'">Apri in '.e(config('app.name')).'</a></p>');
    }
}
