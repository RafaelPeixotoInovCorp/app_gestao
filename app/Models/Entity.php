<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use App\Services\TenantContext;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Entity extends Model
{
    use BelongsToTenant;
    use SoftDeletes;

    protected $fillable = [
        'tenant_id',
        'is_client',
        'is_supplier',
        'name',
        'postal_code',
        'city',
        'country_id',
        'website',
        'email',
        'gdpr_consent',
        'notes',
        'active',
    ];

    protected static function booted(): void
    {
        static::creating(function (Entity $entity): void {
            if (! $entity->number) {
                $entity->number = (int) static::withTrashed()->max('number') + 1;
            }
        });

        /*
         * nif_hash has a UNIQUE constraint across all rows. Soft-deleted rows must release the hash so the same NIF
         * can be stored again; the validator excludes trashed rows, so omitting this caused UNIQUE crashes on insert.
         */
        static::deleted(function (Entity $entity): void {
            if ($entity->isForceDeleting()) {
                return;
            }

            static::withTrashed()
                ->whereKey($entity->getKey())
                ->whereNotNull('deleted_at')
                ->update(['nif_hash' => null]);
        });

        static::restoring(function (Entity $entity): void {
            $entity->nif_hash = static::hashNif($entity->decryptedNif());
        });
    }

    protected function casts(): array
    {
        return [
            'is_client' => 'boolean',
            'is_supplier' => 'boolean',
            'nif_encrypted' => 'encrypted',
            'address_encrypted' => 'encrypted',
            'phone_encrypted' => 'encrypted',
            'mobile_encrypted' => 'encrypted',
            'gdpr_consent' => 'boolean',
            'active' => 'boolean',
        ];
    }

    public static function hashNif(?string $nif): ?string
    {
        if ($nif === null || $nif === '') {
            return null;
        }

        $normalized = strtoupper(preg_replace('/\s+/', '', $nif));

        return hash('sha256', $normalized);
    }

    public function country(): BelongsTo
    {
        return $this->belongsTo(Country::class);
    }

    public function contacts(): MorphMany
    {
        return $this->morphMany(Contact::class, 'contactable');
    }

    public function supplierInvoices(): HasMany
    {
        return $this->hasMany(SupplierInvoice::class);
    }

    public function decryptedNif(): ?string
    {
        try {
            return $this->nif_encrypted;
        } catch (\Throwable) {
            return null;
        }
    }

    public function decryptedPhone(): ?string
    {
        try {
            return $this->phone_encrypted;
        } catch (\Throwable) {
            return null;
        }
    }

    public function decryptedMobile(): ?string
    {
        try {
            return $this->mobile_encrypted;
        } catch (\Throwable) {
            return null;
        }
    }

    public function decryptedAddress(): ?string
    {
        try {
            return $this->address_encrypted;
        } catch (\Throwable) {
            return null;
        }
    }

    public function resolveRouteBinding($value, $field = null)
    {
        $field = $field ?: $this->getRouteKeyName();

        return $this->where($field, $value)
            ->where('tenant_id', app(TenantContext::class)->id())
            ->firstOrFail();
    }
}
