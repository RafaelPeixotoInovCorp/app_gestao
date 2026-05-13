<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SubscriptionAuditLog extends Model
{
    public const ACTION_DOWNGRADE_CLEARED = 'downgrade_cleared';

    public const ACTION_TRIAL_STARTED = 'trial_started';

    public const ACTION_INITIAL_PROVISION = 'initial_provision';

    public const ACTION_UPGRADE = 'upgrade';

    public const ACTION_DOWNGRADE_SCHEDULED = 'downgrade_scheduled';

    public const ACTION_DOWNGRADE_APPLIED = 'downgrade_applied';

    public const ACTION_CANCEL_SCHEDULED = 'cancel_scheduled';

    public const ACTION_CANCEL_IMMEDIATE = 'cancel_immediate';

    public const ACTION_CANCEL_TO_BASIC = 'cancel_to_basic';

    public const ACTION_PERIOD_RENEWED = 'period_renewed';

    public const ACTION_TRIAL_CONVERTED = 'trial_converted';

    public const ACTION_SUBSCRIPTION_ENDED = 'subscription_ended';

    protected $fillable = [
        'tenant_id',
        'actor_user_id',
        'action',
        'from_plan_id',
        'to_plan_id',
        'metadata',
    ];

    protected function casts(): array
    {
        return [
            'metadata' => 'array',
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
     * @return BelongsTo<User, $this>
     */
    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'actor_user_id');
    }
}
