<?php

namespace App\Support;

use Illuminate\Support\Str;

final class RoleLabels
{
    /** @var array<string, string> */
    public const LABELS = [
        'admin' => 'Administrador',
        'basico' => 'Básico',
        'operacional' => 'Operacional',
        'operador' => 'Operacional',
    ];

    public static function display(string $roleName): string
    {
        return self::LABELS[$roleName] ?? Str::title(str_replace('_', ' ', $roleName));
    }

    /** @return array<string, int> Menor = aparece primeiro na lista */
    public static function sortWeight(string $roleName): int
    {
        return match ($roleName) {
            'admin' => 0,
            'basico' => 1,
            'operacional', 'operador' => 2,
            default => 50,
        };
    }
}
