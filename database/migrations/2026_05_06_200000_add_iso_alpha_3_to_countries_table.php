<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('countries', function (Blueprint $table) {
            $table->string('iso_alpha_3', 3)->nullable()->after('iso_alpha_2');
        });

        $iso2to3 = [
            'PT' => 'PRT', 'ES' => 'ESP', 'FR' => 'FRA', 'DE' => 'DEU', 'IT' => 'ITA',
            'NL' => 'NLD', 'BE' => 'BEL', 'LU' => 'LUX', 'GB' => 'GBR', 'IE' => 'IRL',
            'BR' => 'BRA', 'AO' => 'AGO', 'MZ' => 'MOZ', 'CV' => 'CPV', 'GW' => 'GNB',
            'ST' => 'STP', 'TL' => 'TLS',
        ];

        foreach ($iso2to3 as $a2 => $a3) {
            DB::table('countries')->where('iso_alpha_2', $a2)->update(['iso_alpha_3' => $a3]);
        }
    }

    public function down(): void
    {
        Schema::table('countries', function (Blueprint $table) {
            $table->dropColumn('iso_alpha_3');
        });
    }
};
