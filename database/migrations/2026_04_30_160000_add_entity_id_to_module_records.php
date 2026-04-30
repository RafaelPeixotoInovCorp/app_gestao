<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('module_records', function (Blueprint $table) {
            $table->foreignId('entity_id')->nullable()->after('module_key')->constrained('entities')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('module_records', function (Blueprint $table) {
            $table->dropConstrainedForeignId('entity_id');
        });
    }
};
