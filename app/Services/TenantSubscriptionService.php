<?php

namespace App\Services;

use App\Models\BillingLedgerEntry;
use App\Models\Plan;
use App\Models\SubscriptionAuditLog;
use App\Models\Tenant;
use App\Models\TenantSubscription;
use App\Models\User;
use App\Notifications\TrialEndingSoonNotification;
use Illuminate\Support\Facades\DB;

final class TenantSubscriptionService
{
    public function __construct(
        private readonly TenantContext $tenantContext,
    ) {}

    public function provisionNewTenantSubscription(Tenant $tenant): TenantSubscription
    {
        $slug = (string) config('subscription.default_new_tenant_plan_slug', 'basico');
        $plan = Plan::query()->where('slug', $slug)->firstOrFail();
        $now = now()->startOfDay();
        $periodEnd = $now->copy()->addMonth()->subDay();

        return DB::transaction(function () use ($tenant, $plan, $now, $periodEnd): TenantSubscription {
            $sub = TenantSubscription::query()->updateOrCreate(
                ['tenant_id' => $tenant->id],
                [
                    'plan_id' => $plan->id,
                    'status' => TenantSubscription::STATUS_ACTIVE,
                    'trial_ends_at' => null,
                    'current_period_start' => $now->toDateString(),
                    'current_period_end' => $periodEnd->toDateString(),
                    'cancel_at_period_end' => false,
                    'canceled_at' => null,
                    'pending_plan_id' => null,
                    'pending_cycle_credit' => 0,
                    'trial_reminders_sent' => null,
                ]
            );

            $this->writeAudit($tenant->id, null, SubscriptionAuditLog::ACTION_INITIAL_PROVISION, null, $plan->id, [
                'plan_slug' => $plan->slug,
            ]);

            return $sub->fresh(['plan']);
        });
    }

    public function syncExpiredTrialIfNeeded(Tenant $tenant): void
    {
        $sub = $tenant->subscription;
        if (! $sub || $sub->status !== TenantSubscription::STATUS_TRIALING || ! $sub->trial_ends_at) {
            return;
        }
        if (now()->lt($sub->trial_ends_at)) {
            return;
        }

        $this->convertExpiredTrial($sub);
    }

    public function convertExpiredTrial(TenantSubscription $subscription): void
    {
        DB::transaction(function () use ($subscription): void {
            /** @var TenantSubscription $sub */
            $sub = TenantSubscription::query()->whereKey($subscription->id)->lockForUpdate()->first();
            if (! $sub || $sub->status !== TenantSubscription::STATUS_TRIALING) {
                return;
            }
            if ($sub->trial_ends_at && now()->lt($sub->trial_ends_at)) {
                return;
            }

            $fallbackSlug = (string) config('subscription.fallback_plan_after_trial_slug', 'basico');
            $fallback = Plan::query()->where('slug', $fallbackSlug)->firstOrFail();
            $fromPlanId = $sub->plan_id;

            $start = now()->startOfDay();
            $end = $start->copy()->addMonth()->subDay();

            $sub->forceFill([
                'plan_id' => $fallback->id,
                'status' => TenantSubscription::STATUS_ACTIVE,
                'trial_ends_at' => null,
                'current_period_start' => $start->toDateString(),
                'current_period_end' => $end->toDateString(),
                'pending_plan_id' => null,
                'pending_cycle_credit' => 0,
                'trial_reminders_sent' => null,
            ])->save();

            $this->writeAudit($sub->tenant_id, null, SubscriptionAuditLog::ACTION_TRIAL_CONVERTED, $fromPlanId, $fallback->id, []);
            $this->writeLedger($sub, BillingLedgerEntry::TYPE_TRIAL_CONVERSION, 0, 'O trial terminou e a subscrição passou a estar ativa no plano pago; neste passo não houve cobrança extra.');
        });
    }

    public function processBillingCycles(): int
    {
        $this->convertAllExpiredTrials();

        $processed = 0;
        $today = now()->toDateString();

        $ids = TenantSubscription::query()
            ->where('status', TenantSubscription::STATUS_ACTIVE)
            ->whereDate('current_period_end', '<', $today)
            ->pluck('id');

        foreach ($ids as $id) {
            DB::transaction(function () use ($id, &$processed): void {
                /** @var ?TenantSubscription $sub */
                $sub = TenantSubscription::query()->whereKey($id)->lockForUpdate()->first();
                if (! $sub || $sub->status !== TenantSubscription::STATUS_ACTIVE) {
                    return;
                }
                if ($sub->current_period_end->toDateString() >= now()->toDateString()) {
                    return;
                }

                if ($sub->cancel_at_period_end) {
                    $sub->forceFill([
                        'status' => TenantSubscription::STATUS_ENDED,
                        'cancel_at_period_end' => false,
                    ])->save();

                    $this->writeAudit($sub->tenant_id, null, SubscriptionAuditLog::ACTION_SUBSCRIPTION_ENDED, $sub->plan_id, null, [
                        'reason' => 'cancel_at_period_end',
                    ]);
                    $this->writeLedger($sub, BillingLedgerEntry::TYPE_CANCELLATION_POLICY, 0, 'A subscrição terminou no fim do período acordado; não houve cobrança adicional.');
                    $processed++;

                    return;
                }

                $oldPlan = $sub->plan()->first();
                $pendingPlan = $sub->pending_plan_id ? Plan::query()->find($sub->pending_plan_id) : null;

                $newStart = $sub->current_period_end->copy()->addDay()->startOfDay();
                $newEnd = $newStart->copy()->addMonth()->subDay();

                if ($pendingPlan) {
                    $renewalBase = (float) $pendingPlan->price_per_month;
                    $credit = (float) $sub->pending_cycle_credit;
                    $charge = max(0, $renewalBase - $credit);

                    $sub->forceFill([
                        'plan_id' => $pendingPlan->id,
                        'pending_plan_id' => null,
                        'pending_cycle_credit' => 0,
                        'current_period_start' => $newStart->toDateString(),
                        'current_period_end' => $newEnd->toDateString(),
                    ])->save();

                    $this->writeAudit($sub->tenant_id, null, SubscriptionAuditLog::ACTION_DOWNGRADE_APPLIED, $oldPlan?->id, $pendingPlan->id, [
                        'credit_applied' => $credit,
                        'charge' => $charge,
                    ]);
                    $this->writeLedger($sub, BillingLedgerEntry::TYPE_RENEWAL_CHARGE, $charge, 'Renovação: entrou em vigor o plano agendado e descontou-se o crédito acumulado ao valor cobrado.', [
                        'base' => $renewalBase,
                        'credit_applied' => $credit,
                    ]);
                } else {
                    $renewalBase = (float) ($oldPlan?->price_per_month ?? 0);
                    $credit = (float) $sub->pending_cycle_credit;
                    $charge = max(0, $renewalBase - $credit);

                    $sub->forceFill([
                        'pending_cycle_credit' => 0,
                        'current_period_start' => $newStart->toDateString(),
                        'current_period_end' => $newEnd->toDateString(),
                    ])->save();

                    $this->writeAudit($sub->tenant_id, null, SubscriptionAuditLog::ACTION_PERIOD_RENEWED, $oldPlan?->id, $oldPlan?->id, [
                        'charge' => $charge,
                    ]);
                    $this->writeLedger($sub, BillingLedgerEntry::TYPE_RENEWAL_CHARGE, $charge, 'Renovação mensal: início de um novo período de subscrição ao preço do plano atual.', [
                        'base' => $renewalBase,
                        'credit_applied' => $credit,
                    ]);
                }

                $processed++;
            });
        }

        return $processed;
    }

    public function convertAllExpiredTrials(): int
    {
        $count = 0;
        $ids = TenantSubscription::query()
            ->where('status', TenantSubscription::STATUS_TRIALING)
            ->whereNotNull('trial_ends_at')
            ->where('trial_ends_at', '<=', now())
            ->pluck('id');

        foreach ($ids as $id) {
            $sub = TenantSubscription::query()->find($id);
            if ($sub) {
                $this->convertExpiredTrial($sub);
                $count++;
            }
        }

        return $count;
    }

    public function sendTrialReminders(): int
    {
        $sent = 0;
        $daysList = config('subscription.trial_reminder_days_before', [3, 1]);
        if (! is_array($daysList)) {
            return 0;
        }

        $subs = TenantSubscription::query()
            ->where('status', TenantSubscription::STATUS_TRIALING)
            ->whereNotNull('trial_ends_at')
            ->with(['tenant.users'])
            ->get();

        foreach ($subs as $sub) {
            if (! $sub->trial_ends_at || $sub->trial_ends_at->isPast()) {
                continue;
            }

            $daysLeft = (int) now()->startOfDay()->diffInDays($sub->trial_ends_at->copy()->startOfDay());
            $already = $sub->trial_reminders_sent ?? [];

            foreach ($daysList as $d) {
                if ((int) $d !== $daysLeft) {
                    continue;
                }
                if (in_array($daysLeft, $already, true)) {
                    continue;
                }

                $tenant = $sub->tenant;
                if (! $tenant) {
                    continue;
                }

                $previous = $this->tenantContext->tenant();
                $this->tenantContext->set($tenant);
                try {
                    foreach ($tenant->users as $user) {
                        /** @var User $user */
                        if ($user->hasRole('admin')) {
                            $user->notify(new TrialEndingSoonNotification($sub, $daysLeft));
                        }
                    }
                } finally {
                    $this->tenantContext->set($previous);
                }

                $already[] = $daysLeft;
                $sub->forceFill(['trial_reminders_sent' => $already])->save();
                $sent++;
            }
        }

        return $sent;
    }

    public function changePlan(TenantSubscription $subscription, Plan $target, ?User $actor): void
    {
        $subscription->loadMissing('plan');
        $current = $subscription->plan;
        if (! $current) {
            return;
        }

        if ($current->id === $target->id) {
            if ($subscription->pending_plan_id !== null) {
                DB::transaction(function () use ($subscription, $actor, $current): void {
                    $sub = TenantSubscription::query()->whereKey($subscription->id)->lockForUpdate()->firstOrFail();
                    $sub->forceFill([
                        'pending_plan_id' => null,
                        'pending_cycle_credit' => 0,
                    ])->save();
                    $this->writeAudit($sub->tenant_id, $actor?->id, SubscriptionAuditLog::ACTION_DOWNGRADE_CLEARED, $current->id, $current->id, []);
                });
            }

            return;
        }

        $isUpgrade = (float) $target->price_per_month > (float) $current->price_per_month
            || ((float) $target->price_per_month === (float) $current->price_per_month && (int) $target->sort_order > (int) $current->sort_order);

        if ($isUpgrade) {
            $this->applyImmediateUpgrade($subscription, $target, $actor);

            return;
        }

        if ($current->id !== $target->id) {
            $this->scheduleDowngrade($subscription, $target, $actor);
        }
    }

    private function applyImmediateUpgrade(TenantSubscription $subscription, Plan $target, ?User $actor): void
    {
        DB::transaction(function () use ($subscription, $target, $actor): void {
            /** @var TenantSubscription $sub */
            $sub = TenantSubscription::query()->whereKey($subscription->id)->lockForUpdate()->firstOrFail();
            $sub->load('plan');
            $current = $sub->plan;
            if (! $current) {
                return;
            }

            $ratio = $this->remainingPeriodRatio($sub);
            $delta = (float) $target->price_per_month - (float) $current->price_per_month;
            $charge = round($ratio * max(0, $delta), 2);

            $fromId = $current->id;

            $sub->forceFill([
                'plan_id' => $target->id,
                'pending_plan_id' => null,
                'pending_cycle_credit' => 0,
                'status' => $sub->status === TenantSubscription::STATUS_TRIALING
                    ? TenantSubscription::STATUS_TRIALING
                    : TenantSubscription::STATUS_ACTIVE,
            ])->save();

            if ($charge > 0) {
                $this->writeLedger($sub, BillingLedgerEntry::TYPE_UPGRADE_PRORATION, $charge, 'Upgrade imediato: cobrada a diferença de preço em pró-rata até ao fim do período atual.', [
                    'ratio' => $ratio,
                    'delta_monthly' => $delta,
                ]);
            }

            $this->writeAudit($sub->tenant_id, $actor?->id, SubscriptionAuditLog::ACTION_UPGRADE, $fromId, $target->id, [
                'proration_charge' => $charge,
            ]);
        });
    }

    private function scheduleDowngrade(TenantSubscription $subscription, Plan $target, ?User $actor): void
    {
        DB::transaction(function () use ($subscription, $target, $actor): void {
            /** @var TenantSubscription $sub */
            $sub = TenantSubscription::query()->whereKey($subscription->id)->lockForUpdate()->firstOrFail();
            $sub->load('plan');
            $current = $sub->plan;
            if (! $current) {
                return;
            }

            $ratio = $this->remainingPeriodRatio($sub);
            $delta = (float) $current->price_per_month - (float) $target->price_per_month;
            $credit = round($ratio * max(0, $delta), 2);

            $sub->forceFill([
                'pending_plan_id' => $target->id,
                'pending_cycle_credit' => $credit,
            ])->save();

            $this->writeLedger($sub, BillingLedgerEntry::TYPE_DOWNGRADE_SCHEDULED, 0, 'Mudança para um plano mais económico agendada para o próximo ciclo; o crédito estimado será aplicado na próxima renovação.', [
                'target_plan_slug' => $target->slug,
                'credit_next_cycle' => $credit,
                'ratio' => $ratio,
            ]);

            $this->writeAudit($sub->tenant_id, $actor?->id, SubscriptionAuditLog::ACTION_DOWNGRADE_SCHEDULED, $current->id, $target->id, [
                'credit_next_cycle' => $credit,
            ]);
        });
    }

    public function cancelAtPeriodEnd(TenantSubscription $subscription, ?User $actor): void
    {
        DB::transaction(function () use ($subscription, $actor): void {
            $sub = TenantSubscription::query()->whereKey($subscription->id)->lockForUpdate()->firstOrFail();
            if ($sub->status === TenantSubscription::STATUS_ENDED) {
                return;
            }
            $sub->load('plan');
            $current = $sub->plan;
            if (! $current) {
                return;
            }

            if ($this->isFallbackBasicPlan($current)) {
                $sub->forceFill([
                    'cancel_at_period_end' => true,
                    'canceled_at' => now(),
                ])->save();

                $this->writeAudit($sub->tenant_id, $actor?->id, SubscriptionAuditLog::ACTION_CANCEL_SCHEDULED, $sub->plan_id, null, []);
                $this->writeLedger($sub, BillingLedgerEntry::TYPE_CANCELLATION_POLICY, 0, 'Cancelamento pedido: manténs acesso até '.($sub->current_period_end?->toDateString() ?? 'ao fim do período atual').'.');

                return;
            }

            $basico = $this->fallbackBasicoPlan();
            $ratio = $this->remainingPeriodRatio($sub);
            $delta = (float) $current->price_per_month - (float) $basico->price_per_month;
            $credit = round($ratio * max(0, $delta), 2);

            $sub->forceFill([
                'pending_plan_id' => $basico->id,
                'pending_cycle_credit' => $credit,
                'cancel_at_period_end' => false,
                'canceled_at' => null,
            ])->save();

            $this->writeLedger($sub, BillingLedgerEntry::TYPE_CANCELLATION_POLICY, 0, 'No fim do período atual a subscrição passa ao plano básico; o crédito calculado reduz o valor da próxima cobrança.', [
                'target_plan_slug' => $basico->slug,
                'credit_next_cycle' => $credit,
            ]);
            $this->writeAudit($sub->tenant_id, $actor?->id, SubscriptionAuditLog::ACTION_CANCEL_SCHEDULED, $current->id, $basico->id, [
                'activate_basic_next_cycle' => true,
                'credit_next_cycle' => $credit,
            ]);
        });
    }

    public function cancelImmediately(TenantSubscription $subscription, ?User $actor): void
    {
        DB::transaction(function () use ($subscription, $actor): void {
            $sub = TenantSubscription::query()->whereKey($subscription->id)->lockForUpdate()->firstOrFail();
            $sub->load('plan');
            $current = $sub->plan;
            if (! $current) {
                return;
            }

            if ($this->isFallbackBasicPlan($current)) {
                $percent = (float) config('subscription.cancellation.refund_percent_on_immediate_cancel', 0);
                $ratio = $this->remainingPeriodRatio($sub);
                $base = (float) ($current->price_per_month ?? 0);
                $refund = round($base * $ratio * ($percent / 100), 2);

                $sub->forceFill([
                    'status' => TenantSubscription::STATUS_ENDED,
                    'cancel_at_period_end' => false,
                    'canceled_at' => now(),
                    'pending_plan_id' => null,
                    'pending_cycle_credit' => 0,
                ])->save();

                $this->writeAudit($sub->tenant_id, $actor?->id, SubscriptionAuditLog::ACTION_CANCEL_IMMEDIATE, $current->id, null, [
                    'refund_percent' => $percent,
                    'refund_amount' => $refund,
                ]);

                $this->writeLedger($sub, BillingLedgerEntry::TYPE_CANCELLATION_POLICY, -1 * $refund, 'Cancelamento imediato: creditados '.$percent.'% do valor correspondente ao tempo não utilizado do período atual.', [
                    'percent' => $percent,
                    'ratio' => $ratio,
                    'base_monthly' => $base,
                ]);

                return;
            }

            $basico = $this->fallbackBasicoPlan();
            $fromPlanId = $current->id;
            $percent = (float) config('subscription.cancellation.refund_percent_on_immediate_cancel', 0);
            $ratio = $this->remainingPeriodRatio($sub);
            $base = (float) ($current->price_per_month ?? 0);
            $refund = round($base * $ratio * ($percent / 100), 2);

            $start = now()->startOfDay();
            $end = $start->copy()->addMonth()->subDay();

            $sub->forceFill([
                'plan_id' => $basico->id,
                'status' => TenantSubscription::STATUS_ACTIVE,
                'trial_ends_at' => null,
                'current_period_start' => $start->toDateString(),
                'current_period_end' => $end->toDateString(),
                'cancel_at_period_end' => false,
                'canceled_at' => null,
                'pending_plan_id' => null,
                'pending_cycle_credit' => 0,
            ])->save();

            $this->writeAudit($sub->tenant_id, $actor?->id, SubscriptionAuditLog::ACTION_CANCEL_TO_BASIC, $fromPlanId, $basico->id, [
                'refund_percent' => $percent,
                'refund_amount' => $refund,
            ]);

            $this->writeLedger($sub, BillingLedgerEntry::TYPE_CANCELLATION_POLICY, -1 * $refund, 'Passagem imediata ao plano básico; creditados '.$percent.'% do valor correspondente ao tempo não utilizado do período em curso.', [
                'percent' => $percent,
                'ratio' => $ratio,
                'base_monthly' => $base,
            ]);
        });
    }

    public function reactivateWithPlan(TenantSubscription $subscription, Plan $target, ?User $actor): void
    {
        DB::transaction(function () use ($subscription, $target, $actor): void {
            $sub = TenantSubscription::query()->whereKey($subscription->id)->lockForUpdate()->firstOrFail();
            if ($sub->status !== TenantSubscription::STATUS_ENDED) {
                return;
            }

            $start = now()->startOfDay();
            $end = $start->copy()->addMonth()->subDay();

            $sub->forceFill([
                'plan_id' => $target->id,
                'status' => TenantSubscription::STATUS_ACTIVE,
                'trial_ends_at' => null,
                'current_period_start' => $start->toDateString(),
                'current_period_end' => $end->toDateString(),
                'cancel_at_period_end' => false,
                'canceled_at' => null,
                'pending_plan_id' => null,
                'pending_cycle_credit' => 0,
            ])->save();

            $this->writeAudit($sub->tenant_id, $actor?->id, SubscriptionAuditLog::ACTION_UPGRADE, null, $target->id, [
                'reactivation' => true,
            ]);
            $this->writeLedger($sub, BillingLedgerEntry::TYPE_RENEWAL_CHARGE, (float) $target->price_per_month, 'Reativação da subscrição: cobrança do primeiro período ao preço mensal do plano escolhido.');
        });
    }

    private function fallbackBasicoPlan(): Plan
    {
        $slug = (string) config('subscription.fallback_plan_after_trial_slug', 'basico');

        return Plan::query()->where('slug', $slug)->firstOrFail();
    }

    private function isFallbackBasicPlan(Plan $plan): bool
    {
        $slug = (string) config('subscription.fallback_plan_after_trial_slug', 'basico');

        return $plan->slug === $slug;
    }

    private function remainingPeriodRatio(TenantSubscription $sub): float
    {
        $start = $sub->current_period_start->copy()->startOfDay();
        $end = $sub->current_period_end->copy()->endOfDay();
        $now = now()->startOfDay();
        if ($now->gt($end)) {
            return 0;
        }
        $total = max(1, $start->diffInDays($end) + 1);
        $remaining = $now->diffInDays($end) + 1;

        return min(1, max(0, $remaining / $total));
    }

    private function writeLedger(TenantSubscription $sub, string $type, float $amount, string $description, ?array $metadata = null): void
    {
        BillingLedgerEntry::query()->create([
            'tenant_id' => $sub->tenant_id,
            'tenant_subscription_id' => $sub->id,
            'entry_type' => $type,
            'amount' => $amount,
            'currency' => (string) config('subscription.currency', 'EUR'),
            'description' => $description,
            'metadata' => $metadata,
        ]);
    }

    private function writeAudit(int $tenantId, ?int $actorId, string $action, ?int $fromPlanId, ?int $toPlanId, array $metadata): void
    {
        SubscriptionAuditLog::query()->create([
            'tenant_id' => $tenantId,
            'actor_user_id' => $actorId,
            'action' => $action,
            'from_plan_id' => $fromPlanId,
            'to_plan_id' => $toPlanId,
            'metadata' => $metadata === [] ? null : $metadata,
        ]);
    }
}
