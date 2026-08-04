<?php

namespace App\Notifications;

use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Facades\Schema;
use NotificationChannels\WebPush\WebPushChannel;
use NotificationChannels\WebPush\WebPushMessage;

class WorkflowNotification extends Notification
{
    use Queueable;

    public function __construct(
        public readonly string $title,
        public readonly string $message,
        public readonly string $url,
    ) {}

    public function via(object $notifiable): array
    {
        $channels = ['database'];
        $pushTable = (string) config('webpush.table_name', 'push_subscriptions');
        $pushConfigured = filled(config('webpush.vapid.public_key'))
            && filled(config('webpush.vapid.private_key'));

        if ($notifiable instanceof User
            && $notifiable->usesDriverWorkspace()
            && $pushConfigured
            && Schema::hasTable($pushTable)
            && $notifiable->pushSubscriptions()->exists()
        ) {
            $channels[] = WebPushChannel::class;
        }

        return $channels;
    }

    public function toWebPush(object $notifiable, Notification $notification): WebPushMessage
    {
        return (new WebPushMessage)
            ->title($this->title)
            ->body($this->message)
            ->icon('/icons/gafco-driver-192.png')
            ->badge('/icons/gafco-driver-192.png')
            ->lang('ro')
            ->tag('workflow-'.sha1($this->url.'|'.$this->title))
            ->vibrate([180, 80, 180])
            ->data(['url' => $this->notificationPath()])
            ->action('Deschide', 'open')
            ->options(['TTL' => 3600, 'urgency' => 'high']);
    }

    public function toArray(object $notifiable): array
    {
        return [
            'title' => $this->title,
            'message' => $this->message,
            'url' => $this->url,
        ];
    }

    private function notificationPath(): string
    {
        if (str_starts_with($this->url, '/')) {
            return $this->url;
        }

        $path = parse_url($this->url, PHP_URL_PATH) ?: '/notificari';
        $query = parse_url($this->url, PHP_URL_QUERY);

        return $query ? $path.'?'.$query : $path;
    }
}
