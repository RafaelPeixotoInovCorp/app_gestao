<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('roles')->where('name', 'operador')->update(['name' => 'operacional']);
    }

    public function down(): void
    {
        DB::table('roles')->where('name', 'operacional')->update(['name' => 'operador']);
    }
};
