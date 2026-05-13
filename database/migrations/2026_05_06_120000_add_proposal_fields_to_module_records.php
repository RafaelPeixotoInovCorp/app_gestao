<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('module_records', function (Blueprint $table) {
            $table->string('proposal_number', 64)->nullable()->after('description');
            $table->date('proposal_date')->nullable()->after('proposal_number');
            $table->date('valid_until')->nullable()->after('proposal_date');
            $table->decimal('proposal_amount', 15, 2)->nullable()->after('valid_until');
            $table->string('proposal_status', 32)->nullable()->after('proposal_amount');
        });
    }

    public function down(): void
    {
        Schema::table('module_records', function (Blueprint $table) {
            $table->dropColumn([
                'proposal_number',
                'proposal_date',
                'valid_until',
                'proposal_amount',
                'proposal_status',
            ]);
        });
    }
};
