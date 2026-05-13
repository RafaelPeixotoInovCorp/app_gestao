<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Plan extends Model
{
    protected $fillable = [
        'name',
        'slug',
        'description',
        'price_per_month',
        'max_users',
        'trial_days',
        'has_premium_modules',
        'is_public',
        'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'price_per_month' => 'decimal:2',
            'max_users' => 'integer',
            'trial_days' => 'integer',
            'has_premium_modules' => 'boolean',
            'is_public' => 'boolean',
            'sort_order' => 'integer',
        ];
    }

    /**
     * @return HasMany<TenantSubscription, $this>
     */
    public function subscriptions(): HasMany
    {
        return $this->hasMany(TenantSubscription::class, 'plan_id');
    }

    public function isUnlimitedUsers(): bool
    {
        return $this->max_users === null;
    }
}
