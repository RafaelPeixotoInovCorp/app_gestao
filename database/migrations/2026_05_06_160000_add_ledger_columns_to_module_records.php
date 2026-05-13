<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('module_records', function (Blueprint $table) {
            $table->date('ledger_entry_date')->nullable()->after('order_status');
            $table->decimal('ledger_debit', 15, 2)->nullable()->after('ledger_entry_date');
            $table->decimal('ledger_credit', 15, 2)->nullable()->after('ledger_debit');
        });
    }

    public function down(): void
    {
        Schema::table('module_records', function (Blueprint $table) {
            $table->dropColumn(['ledger_entry_date', 'ledger_debit', 'ledger_credit']);
        });
    }
};
