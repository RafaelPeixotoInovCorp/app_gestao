<?php

namespace App\Console\Commands;

use App\Services\TenantSubscriptionService;
use Illuminate\Console\Command;

class ProcessSubscriptionCyclesCommand extends Command
{
    protected $signature = 'subscriptions:process-cycles';

    protected $description = 'Processa renovações, fim de período, cancelamentos agendados e trials expirados (faturação genérica).';

    public function handle(TenantSubscriptionService $service): int
    {
        $cycles = $service->processBillingCycles();
        $this->info("Ciclos e trials processados (agregado): {$cycles}");

        return self::SUCCESS;
    }
}
