<?php

namespace App\Notifications;

use App\Models\OperationalAlert;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class OperationalAlertNotification extends Notification
{
    use Queueable;

    public function __construct(public readonly OperationalAlert $alert) {}

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toArray(object $notifiable): array
    {
        return [
            'title' => $this->alert->title,
            'message' => $this->alert->message,
            'url' => $this->alert->url,
            'category' => 'operational_alert',
            'operational_alert_id' => $this->alert->id,
            'severity' => $this->alert->severity,
        ];
    }
}
