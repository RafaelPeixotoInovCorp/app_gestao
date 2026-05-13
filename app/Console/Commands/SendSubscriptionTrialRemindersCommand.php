<?php

namespace App\Console\Commands;

use App\Services\TenantSubscriptionService;
use Illuminate\Console\Command;

class SendSubscriptionTrialRemindersCommand extends Command
{
    protected $signature = 'subscriptions:trial-reminders';

    protected $description = 'Envia notificações antes do fim do trial (dias configuráveis).';

    public function handle(TenantSubscriptionService $service): int
    {
        $n = $service->sendTrialReminders();
        $this->info("Lembretes enviados: {$n}");

        return self::SUCCESS;
    }
}
