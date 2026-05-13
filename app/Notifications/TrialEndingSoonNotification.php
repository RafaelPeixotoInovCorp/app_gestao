<?php

namespace App\Notifications;

use App\Models\TenantSubscription;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class TrialEndingSoonNotification extends Notification
{
    use Queueable;

    public function __construct(
        public TenantSubscription $subscription,
        public int $daysLeft,
    ) {}

    /**
     * @return array<int, string>
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
            'title' => 'Trial a terminar',
            'body' => 'Faltam '.$this->daysLeft.' dia(s) para o fim do período de trial da sua organização.',
            'tenant_id' => $this->subscription->tenant_id,
            'days_left' => $this->daysLeft,
            'trial_ends_at' => $this->subscription->trial_ends_at?->toIso8601String(),
        ];
    }
}
