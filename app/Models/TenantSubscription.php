<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

class TenantSubscription extends Model
{
    public const STATUS_TRIALING = 'trialing';

    public const STATUS_ACTIVE = 'active';

    public const STATUS_ENDED = 'ended';

    protected $fillable = [
        'tenant_id',
        'plan_id',
        'status',
        'trial_ends_at',
        'current_period_start',
        'current_period_end',
        'cancel_at_period_end',
        'canceled_at',
        'pending_plan_id',
        'pending_cycle_credit',
        'trial_reminders_sent',
    ];

    protected function casts(): array
    {
        return [
            'trial_ends_at' => 'datetime',
            'current_period_start' => 'date',
            'current_period_end' => 'date',
            'cancel_at_period_end' => 'boolean',
            'canceled_at' => 'datetime',
            'pending_cycle_credit' => 'decimal:2',
            'trial_reminders_sent' => 'array',
        ];
    }

    /**
     * @return BelongsTo<Tenant, $this>
     */
    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    /**
     * @return BelongsTo<Plan, $this>
     */
    public function plan(): BelongsTo
    {
        return $this->belongsTo(Plan::class, 'plan_id');
    }

    /**
     * @return BelongsTo<Plan, $this>
     */
    public function pendingPlan(): BelongsTo
    {
        return $this->belongsTo(Plan::class, 'pending_plan_id');
    }

    /**
     * @return HasMany<BillingLedgerEntry, $this>
     */
    public function ledgerEntries(): HasMany
    {
        return $this->hasMany(BillingLedgerEntry::class, 'tenant_subscription_id');
    }

    public function hasAccess(): bool
    {
        if ($this->status === self::STATUS_ENDED) {
            return false;
        }

        if ($this->status === self::STATUS_TRIALING && $this->trial_ends_at) {
            return now()->lt($this->trial_ends_at);
        }

        return now()->toDateString() <= $this->current_period_end->toDateString();
    }

    public function accessExpiresOn(): ?Carbon
    {
        if ($this->status === self::STATUS_TRIALING && $this->trial_ends_at) {
            return $this->trial_ends_at->copy()->startOfDay();
        }

        return $this->current_period_end?->copy()->endOfDay();
    }

    public function isTrialing(): bool
    {
        return $this->status === self::STATUS_TRIALING;
    }
}
