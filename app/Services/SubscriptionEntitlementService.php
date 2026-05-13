<?php

namespace App\Services;

use App\Models\Tenant;
use App\Models\TenantSubscription;
use Illuminate\Support\Facades\Config;

final class SubscriptionEntitlementService
{
    public function currentSubscription(Tenant $tenant): ?TenantSubscription
    {
        return $tenant->subscription()->with('plan')->first();
    }

    public function hasAccess(Tenant $tenant): bool
    {
        $sub = $this->currentSubscription($tenant);
        if (! $sub) {
            return false;
        }

        return $sub->hasAccess();
    }

    public function canAccessModule(Tenant $tenant, string $moduleKey): bool
    {
        if (! $this->hasAccess($tenant)) {
            return false;
        }

        $sub = $this->currentSubscription($tenant);
        if (! $sub?->plan) {
            return false;
        }

        $premiumKeys = Config::get('subscription.premium_module_keys', []);
        if ($sub->plan->has_premium_modules) {
            return true;
        }

        return ! in_array($moduleKey, $premiumKeys, true);
    }

    public function userCount(Tenant $tenant): int
    {
        return $tenant->users()->count();
    }

    public function maxUsers(Tenant $tenant): ?int
    {
        $sub = $this->currentSubscription($tenant);
        if (! $sub?->plan) {
            return null;
        }

        return $sub->plan->max_users;
    }

    public function canAddUser(Tenant $tenant): bool
    {
        $max = $this->maxUsers($tenant);
        if ($max === null) {
            return true;
        }

        return $this->userCount($tenant) < $max;
    }

    /**
     * @return array<string, mixed>
     */
    public function summaryForTenant(Tenant $tenant): array
    {
        $sub = $this->currentSubscription($tenant);
        if (! $sub) {
            return [
                'has_subscription' => false,
                'has_access' => false,
            ];
        }

        $sub->loadMissing('plan', 'pendingPlan');
        $plan = $sub->plan;
        $daysTrialLeft = null;
        if ($sub->isTrialing() && $sub->trial_ends_at && $sub->trial_ends_at->isFuture()) {
            $daysTrialLeft = (int) now()->startOfDay()->diffInDays($sub->trial_ends_at->copy()->startOfDay());
        }

        return [
            'has_subscription' => true,
            'has_access' => $sub->hasAccess(),
            'status' => $sub->status,
            'plan' => $plan ? [
                'id' => $plan->id,
                'name' => $plan->name,
                'slug' => $plan->slug,
                'has_premium_modules' => $plan->has_premium_modules,
                'max_users' => $plan->max_users,
                'price_per_month' => (string) $plan->price_per_month,
            ] : null,
            'pending_plan' => $sub->pendingPlan ? [
                'id' => $sub->pendingPlan->id,
                'name' => $sub->pendingPlan->name,
                'slug' => $sub->pendingPlan->slug,
            ] : null,
            'pending_cycle_credit' => (string) $sub->pending_cycle_credit,
            'cancel_at_period_end' => $sub->cancel_at_period_end,
            'current_period_start' => $sub->current_period_start?->toDateString(),
            'current_period_end' => $sub->current_period_end?->toDateString(),
            'trial_ends_at' => $sub->trial_ends_at?->toIso8601String(),
            'days_trial_left' => $daysTrialLeft,
            'users_count' => $this->userCount($tenant),
            'max_users' => $plan?->max_users,
            'unlimited_users' => $plan?->isUnlimitedUsers() ?? false,
        ];
    }
}
