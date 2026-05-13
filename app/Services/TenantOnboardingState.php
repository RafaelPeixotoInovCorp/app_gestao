<?php

namespace App\Services;

use App\Models\Tenant;
use App\Models\User;
use Illuminate\Support\Arr;

final class TenantOnboardingState
{
    /**
     * @return array<string, mixed>|null
     */
    public function summary(?Tenant $tenant, ?User $user): ?array
    {
        if (! $tenant instanceof Tenant || ! $user) {
            return null;
        }

        $settings = $tenant->settings ?? [];
        if (! is_array($settings) || ! Arr::has($settings, 'onboarding')) {
            return null;
        }

        $defaults = TenantOnboardingBootstrap::initialSettings()['onboarding'];
        $onboarding = array_replace_recursive($defaults, Arr::get($settings, 'onboarding', []));
        $checklist = array_merge($defaults['checklist'], Arr::get($onboarding, 'checklist', []));

        $wizardCompleted = Arr::get($onboarding, 'wizard_completed_at') !== null
            && Arr::get($onboarding, 'wizard_completed_at') !== '';

        $total = count($checklist);
        $done = count(array_filter($checklist));

        $publicName = trim((string) Arr::get($settings, 'branding.display_name', ''));
        if ($publicName === '') {
            $publicName = $tenant->name;
        }

        return [
            'wizard_completed' => $wizardCompleted,
            'checklist' => $checklist,
            'checklist_done' => $done,
            'checklist_total' => $total,
            'setup_wizard_url' => route('tenants.setup.wizard'),
            'public_display_name' => $publicName,
        ];
    }

    /**
     * @param  array<string, bool>  $checklistPatch
     */
    public function mergeChecklist(Tenant $tenant, array $checklistPatch): void
    {
        $settings = $tenant->settings ?? [];
        $defaults = TenantOnboardingBootstrap::initialSettings()['onboarding'];
        $onboarding = array_replace_recursive($defaults, Arr::get($settings, 'onboarding', []));
        $checklist = array_merge($defaults['checklist'], Arr::get($onboarding, 'checklist', []), $checklistPatch);
        Arr::set($onboarding, 'checklist', $checklist);
        Arr::set($settings, 'onboarding', $onboarding);
        $tenant->update(['settings' => $settings]);
    }

    public function mergeBranding(Tenant $tenant, array $branding): void
    {
        $settings = $tenant->settings ?? [];
        $defaults = TenantOnboardingBootstrap::initialSettings()['branding'];
        $merged = array_merge($defaults, Arr::get($settings, 'branding', []), $branding);
        if (($merged['accent'] ?? '') === '') {
            $merged['accent'] = $defaults['accent'];
        }
        Arr::set($settings, 'branding', $merged);
        $tenant->update(['settings' => $settings]);
    }

    public function markWizardCompleted(Tenant $tenant): void
    {
        $settings = $tenant->settings ?? [];
        $defaults = TenantOnboardingBootstrap::initialSettings()['onboarding'];
        $onboarding = array_replace_recursive($defaults, Arr::get($settings, 'onboarding', []));
        $onboarding['wizard_completed_at'] = now()->toIso8601String();
        Arr::set($settings, 'onboarding', $onboarding);
        $tenant->update(['settings' => $settings]);
    }
}
