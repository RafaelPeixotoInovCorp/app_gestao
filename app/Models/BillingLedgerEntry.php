<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BillingLedgerEntry extends Model
{
    public const TYPE_UPGRADE_PRORATION = 'upgrade_proration';

    public const TYPE_DOWNGRADE_SCHEDULED = 'downgrade_scheduled';

    public const TYPE_DOWNGRADE_CYCLE_CREDIT = 'downgrade_cycle_credit';

    public const TYPE_RENEWAL_CHARGE = 'renewal_charge';

    public const TYPE_TRIAL_CONVERSION = 'trial_conversion';

    public const TYPE_CANCELLATION_POLICY = 'cancellation_policy';

    public const TYPE_IMMEDIATE_CANCEL_REFUND = 'immediate_cancel_refund';

    protected $fillable = [
        'tenant_id',
        'tenant_subscription_id',
        'entry_type',
        'amount',
        'currency',
        'description',
        'metadata',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:4',
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
     * @return BelongsTo<TenantSubscription, $this>
     */
    public function subscription(): BelongsTo
    {
        return $this->belongsTo(TenantSubscription::class, 'tenant_subscription_id');
    }

    /** Rótulo curto para interfaces (ex.: registo de faturação). */
    public static function labelForType(string $type): string
    {
        return match ($type) {
            self::TYPE_UPGRADE_PRORATION => 'Ajuste por upgrade (pró-rata)',
            self::TYPE_DOWNGRADE_SCHEDULED => 'Plano inferior a partir do próximo ciclo',
            self::TYPE_DOWNGRADE_CYCLE_CREDIT => 'Crédito na renovação (mudança de plano)',
            self::TYPE_RENEWAL_CHARGE => 'Renovação do período',
            self::TYPE_TRIAL_CONVERSION => 'Fim do trial — subscrição ativa',
            self::TYPE_CANCELLATION_POLICY => 'Cancelamento ou ajuste de período',
            self::TYPE_IMMEDIATE_CANCEL_REFUND => 'Crédito por cancelamento imediato',
            default => 'Movimento de faturação',
        };
    }
}
