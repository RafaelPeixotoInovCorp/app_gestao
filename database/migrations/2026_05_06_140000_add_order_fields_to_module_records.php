<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('module_records', function (Blueprint $table) {
            $table->string('order_number', 64)->nullable()->after('proposal_status');
            $table->date('order_date')->nullable()->after('order_number');
            $table->date('order_valid_until')->nullable()->after('order_date');
            $table->decimal('order_amount', 15, 2)->nullable()->after('order_valid_until');
            $table->string('order_status', 32)->nullable()->after('order_amount');
        });
    }

    public function down(): void
    {
        Schema::table('module_records', function (Blueprint $table) {
            $table->dropColumn([
                'order_number',
                'order_date',
                'order_valid_until',
                'order_amount',
                'order_status',
            ]);
        });
    }
};
