<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class BankAccount extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'name',
        'iban_encrypted',
        'swift_encrypted',
        'currency',
        'active',
    ];

    protected function casts(): array
    {
        return [
            'iban_encrypted' => 'encrypted',
            'swift_encrypted' => 'encrypted',
            'active' => 'boolean',
        ];
    }
}
