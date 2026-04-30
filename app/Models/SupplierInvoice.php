<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class SupplierInvoice extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'entity_id',
        'number',
        'amount',
        'tax_amount',
        'invoice_date',
        'due_date',
        'status',
    ];

    public function entity(): BelongsTo
    {
        return $this->belongsTo(Entity::class);
    }
}
