<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class Contact extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'contactable_type',
        'contactable_id',
        'name',
        'email',
        'phone_encrypted',
        'contact_role_id',
    ];

    protected function casts(): array
    {
        return [
            'phone_encrypted' => 'encrypted',
        ];
    }

    public function contactable(): MorphTo
    {
        return $this->morphTo();
    }
}
