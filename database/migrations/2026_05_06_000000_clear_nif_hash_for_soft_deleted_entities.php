<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * UNIQUE(nif_hash) applies to soft-deleted rows too; release hashes so new entities can reuse those NIFs.
     */
    public function up(): void
    {
        DB::table('entities')->whereNotNull('deleted_at')->update(['nif_hash' => null]);
    }

    public function down(): void
    {
        // Hashes cannot be recomputed here without decrypting nif_encrypted per row.
    }
};
