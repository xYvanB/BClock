<?php

declare(strict_types=1);

namespace App\Mail;

use App\Notifications\AppNotification;
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

    /**
     * @param  array<string, string|int|float>  $params
     */
    public function __construct(public string $messageKey, public array $params, public string $url)
    {
        $this->afterCommit();
    }

    private function message(): string
    {
        return AppNotification::render($this->messageKey, $this->params);
    }

    public function envelope(): Envelope
    {
        return new Envelope(subject: config('app.name').': '.$this->message());
    }

    public function content(): Content
    {
        return new Content(htmlString: '<p>'.e($this->message()).'</p><p><a href="'.e($this->url).'">'.e(__('Apri in :app', ['app' => config('app.name')])).'</a></p>');
    }
}
