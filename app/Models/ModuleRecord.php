<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use App\Services\TenantContext;
use App\Support\OrderStatus;
use App\Support\ProposalStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ModuleRecord extends Model
{
    use BelongsToTenant;

    protected $fillable = [
        'tenant_id',
        'module_key',
        'entity_id',
        'title',
        'description',
        'active',
        'proposal_number',
        'proposal_date',
        'valid_until',
        'proposal_amount',
        'proposal_status',
        'order_number',
        'order_date',
        'order_valid_until',
        'order_amount',
        'order_status',
        'ledger_entry_date',
        'ledger_debit',
        'ledger_credit',
        'bank_name',
        'bank_iban_encrypted',
        'bank_swift_encrypted',
    ];

    protected function casts(): array
    {
        return [
            'active' => 'boolean',
            'proposal_date' => 'date',
            'valid_until' => 'date',
            'proposal_amount' => 'decimal:2',
            'proposal_status' => ProposalStatus::class,
            'order_date' => 'date',
            'order_valid_until' => 'date',
            'order_amount' => 'decimal:2',
            'order_status' => OrderStatus::class,
            'ledger_entry_date' => 'date',
            'ledger_debit' => 'decimal:2',
            'ledger_credit' => 'decimal:2',
            'bank_iban_encrypted' => 'encrypted',
            'bank_swift_encrypted' => 'encrypted',
        ];
    }

    public function getIbanAttribute(): ?string
    {
        return $this->bank_iban_encrypted;
    }

    public function getSwiftAttribute(): ?string
    {
        return $this->bank_swift_encrypted;
    }

    public function entity(): BelongsTo
    {
        return $this->belongsTo(Entity::class);
    }

    public function resolveRouteBinding($value, $field = null)
    {
        $field = $field ?: $this->getRouteKeyName();

        return $this->where($field, $value)
            ->where('tenant_id', app(TenantContext::class)->id())
            ->firstOrFail();
    }
}
