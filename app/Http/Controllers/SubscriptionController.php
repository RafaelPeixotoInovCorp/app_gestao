<?php

namespace App\Http\Controllers;

use App\Models\BillingLedgerEntry;
use App\Models\Plan;
use App\Models\SubscriptionAuditLog;
use App\Models\TenantSubscription;
use App\Services\SubscriptionEntitlementService;
use App\Services\TenantContext;
use App\Services\TenantSubscriptionService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class SubscriptionController extends Controller
{
    public function __construct(
        private readonly TenantContext $tenantContext,
        private readonly SubscriptionEntitlementService $entitlements,
        private readonly TenantSubscriptionService $subscriptionService,
    ) {}

    public function dashboard(Request $request): Response
    {
        $tenant = $this->tenantContext->tenant();
        abort_if($tenant === null, 403);

        $subscription = $tenant->subscription()->with(['plan', 'pendingPlan'])->first();
        abort_if($subscription === null, 404);

        $plans = Plan::query()->where('is_public', true)->orderBy('sort_order')->get();

        $ledger = $subscription->ledgerEntries()
            ->latest()
            ->limit(25)
            ->get()
            ->map(fn ($row): array => [
                'id' => $row->id,
                'entry_type' => $row->entry_type,
                'entry_label' => BillingLedgerEntry::labelForType((string) $row->entry_type),
                'amount' => (string) $row->amount,
                'currency' => $row->currency,
                'description' => $row->description,
                'created_at' => $row->created_at?->toIso8601String(),
            ]);

        $audit = SubscriptionAuditLog::query()
            ->where('tenant_id', $tenant->id)
            ->with(['actor:id,name'])
            ->latest()
            ->limit(40)
            ->get()
            ->map(fn ($row): array => [
                'id' => $row->id,
                'action' => $row->action,
                'actor' => $row->actor ? ['id' => $row->actor->id, 'name' => $row->actor->name] : null,
                'from_plan_id' => $row->from_plan_id,
                'to_plan_id' => $row->to_plan_id,
                'metadata' => $row->metadata,
                'created_at' => $row->created_at?->toIso8601String(),
            ]);

        return Inertia::render('Subscription/Dashboard', [
            'summary' => $this->entitlements->summaryForTenant($tenant),
            'plans' => $plans->map(fn (Plan $p): array => [
                'id' => $p->id,
                'name' => $p->name,
                'slug' => $p->slug,
                'description' => $p->description,
                'price_per_month' => (string) $p->price_per_month,
                'max_users' => $p->max_users,
                'has_premium_modules' => $p->has_premium_modules,
                'trial_days' => $p->trial_days,
            ]),
            'ledger' => $ledger,
            'audit' => $audit,
            'canManage' => Gate::allows('manage-subscription'),
            'currency' => config('subscription.currency', 'EUR'),
        ]);
    }

    public function changePlan(Request $request): RedirectResponse
    {
        Gate::authorize('manage-subscription');

        $tenant = $this->tenantContext->tenant();
        abort_if($tenant === null, 403);

        $data = $request->validate([
            'plan_id' => ['required', 'integer', 'exists:plans,id'],
        ]);

        $target = Plan::query()->whereKey($data['plan_id'])->where('is_public', true)->firstOrFail();
        $subscription = $tenant->subscription;
        abort_if(! $subscription instanceof TenantSubscription, 404);

        if ($subscription->status === TenantSubscription::STATUS_ENDED) {
            $this->subscriptionService->reactivateWithPlan($subscription, $target, $request->user());
        } else {
            $this->subscriptionService->changePlan($subscription, $target, $request->user());
        }

        return redirect()->route('subscription.dashboard')->with('success', 'Plano atualizado.');
    }

    public function cancel(Request $request): RedirectResponse
    {
        Gate::authorize('manage-subscription');

        $tenant = $this->tenantContext->tenant();
        abort_if($tenant === null, 403);

        $data = $request->validate([
            'immediate' => ['required', 'boolean'],
        ]);

        $subscription = $tenant->subscription;
        abort_if(! $subscription instanceof TenantSubscription, 404);

        $subscription->loadMissing('plan');
        $fallbackSlug = (string) config('subscription.fallback_plan_after_trial_slug', 'basico');
        $isBasicPlan = $subscription->plan?->slug === $fallbackSlug;

        if ($data['immediate']) {
            $this->subscriptionService->cancelImmediately($subscription, $request->user());
            $message = $isBasicPlan
                ? 'Subscrição terminada. Os valores aplicados ficam registados no histórico de faturação.'
                : 'Plano alterado para o básico. Os valores aplicados ficam registados no histórico de faturação.';
        } else {
            $this->subscriptionService->cancelAtPeriodEnd($subscription, $request->user());
            $message = $isBasicPlan
                ? 'Cancelamento agendado para o fim do período em curso.'
                : 'Passagem ao plano básico agendada para o fim do período em curso.';
        }

        return redirect()->route('subscription.dashboard')->with('success', $message);
    }
}
