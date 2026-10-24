<?php

declare(strict_types=1);

namespace App\Notifications;

use App\Enum\NotificationKind;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Facades\Lang;

/**
 * In-app notification (bell). The e-mail is sent by NotifyUsers, outside transactions, only if the person wants it.
 */
class AppNotification extends Notification
{
    /**
     * @param  array<string, string|int|float>  $params
     */
    public function __construct(
        public NotificationKind $kind,
        public string $messageKey,
        public array $params,
        public string $url,
        public ?string $actorName = null,
    ) {}

    /**
     * Renders the message in the given language (the current one by default). Parameters that are words with a
     * translation of their own (such as the absence type) are translated too.
     *
     * @param  array<string, string|int|float>  $params
     */
    public static function render(string $messageKey, array $params, ?string $locale = null): string
    {
        $translated = array_map(fn (string|int|float $value): string|int|float => is_string($value) && Lang::has($value, $locale) ? __($value, [], $locale) : $value, $params);

        return __($messageKey, $translated, $locale);
    }

    /**
     * @return list<string>
     */
    public function via(object $notifiable): array
    {
        return ['database'];
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return [
            'kind' => $this->kind->value,
            'message' => self::render($this->messageKey, $this->params),
            'message_key' => $this->messageKey,
            'params' => $this->params,
            'url' => $this->url,
            'actor_name' => $this->actorName,
        ];
    }
}
