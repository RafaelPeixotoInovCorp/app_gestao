<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('module_records', function (Blueprint $table) {
            $table->string('bank_name', 255)->nullable()->after('ledger_credit');
            $table->text('bank_iban_encrypted')->nullable()->after('bank_name');
            $table->text('bank_swift_encrypted')->nullable()->after('bank_iban_encrypted');
        });
    }

    public function down(): void
    {
        Schema::table('module_records', function (Blueprint $table) {
            $table->dropColumn(['bank_name', 'bank_iban_encrypted', 'bank_swift_encrypted']);
        });
    }
};
