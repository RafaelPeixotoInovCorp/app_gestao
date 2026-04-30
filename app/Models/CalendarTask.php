<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CalendarTask extends Model
{
    protected $fillable = [
        'title',
        'notes',
        'due_date',
        'is_done',
    ];

    protected function casts(): array
    {
        return [
            'due_date' => 'date',
            'is_done' => 'boolean',
        ];
    }
}
