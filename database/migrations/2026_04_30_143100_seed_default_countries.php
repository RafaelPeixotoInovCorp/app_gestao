<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('countries')) {
            return;
        }

        $now = now();

        $countries = [
            ['name' => 'Portugal', 'iso_alpha_2' => 'PT'],
            ['name' => 'Espanha', 'iso_alpha_2' => 'ES'],
            ['name' => 'França', 'iso_alpha_2' => 'FR'],
            ['name' => 'Alemanha', 'iso_alpha_2' => 'DE'],
            ['name' => 'Itália', 'iso_alpha_2' => 'IT'],
            ['name' => 'Países Baixos', 'iso_alpha_2' => 'NL'],
            ['name' => 'Bélgica', 'iso_alpha_2' => 'BE'],
            ['name' => 'Luxemburgo', 'iso_alpha_2' => 'LU'],
            ['name' => 'Reino Unido', 'iso_alpha_2' => 'GB'],
            ['name' => 'Irlanda', 'iso_alpha_2' => 'IE'],
            ['name' => 'Brasil', 'iso_alpha_2' => 'BR'],
            ['name' => 'Angola', 'iso_alpha_2' => 'AO'],
            ['name' => 'Moçambique', 'iso_alpha_2' => 'MZ'],
            ['name' => 'Cabo Verde', 'iso_alpha_2' => 'CV'],
            ['name' => 'Guiné-Bissau', 'iso_alpha_2' => 'GW'],
            ['name' => 'São Tomé e Príncipe', 'iso_alpha_2' => 'ST'],
            ['name' => 'Timor-Leste', 'iso_alpha_2' => 'TL'],
        ];

        foreach ($countries as $country) {
            $exists = DB::table('countries')
                ->where('iso_alpha_2', $country['iso_alpha_2'])
                ->exists();

            if ($exists) {
                DB::table('countries')
                    ->where('iso_alpha_2', $country['iso_alpha_2'])
                    ->update([
                        'name' => $country['name'],
                        'updated_at' => $now,
                    ]);
            } else {
                DB::table('countries')->insert([
                    'name' => $country['name'],
                    'iso_alpha_2' => $country['iso_alpha_2'],
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
            }
        }
    }

    public function down(): void
    {
        // Intencionalmente vazio para não remover países já usados.
    }
};
