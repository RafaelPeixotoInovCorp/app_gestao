<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Planos por defeito
    |--------------------------------------------------------------------------
    */
    'default_trial_plan_slug' => env('SUBSCRIPTION_TRIAL_PLAN_SLUG', 'profissional'),

    'fallback_plan_after_trial_slug' => env('SUBSCRIPTION_FALLBACK_PLAN_SLUG', 'basico'),

    /** Plano atribuído automaticamente a organizações recém-criadas (sem trial). */
    'default_new_tenant_plan_slug' => env('SUBSCRIPTION_DEFAULT_NEW_TENANT_PLAN_SLUG', 'basico'),

    /*
    |--------------------------------------------------------------------------
    | Avisos de fim de trial (dias antes do termo)
    |--------------------------------------------------------------------------
    */
    'trial_reminder_days_before' => [3, 1],

    'currency' => env('SUBSCRIPTION_CURRENCY', 'EUR'),

    /*
    |--------------------------------------------------------------------------
    | Chaves de módulo considerados "premium" (gestão automática de acesso)
    |--------------------------------------------------------------------------
    */
    'premium_module_keys' => [
        'financial-bank-accounts',
        'financial-customer-ledger',
        'financial-supplier-invoices',
        'digital-archive',
    ],

    /*
    |--------------------------------------------------------------------------
    | Regras genéricas de faturação em cancelamento a meio do ciclo
    |--------------------------------------------------------------------------
    | refund_percent_on_immediate_cancel: percentagem do valor do período em
    | curso creditada em conta (genérico; sem gateway de pagamento).
    */
    'cancellation' => [
        'refund_percent_on_immediate_cancel' => (float) env('SUBSCRIPTION_IMMEDIATE_CANCEL_REFUND_PERCENT', 0),
    ],

];
