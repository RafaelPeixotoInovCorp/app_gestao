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
            if (! Schema::hasColumn('countries', 'name')) {
                $table->string('name')->nullable()->after('id');
            }
            if (! Schema::hasColumn('countries', 'iso_alpha_2')) {
                $table->string('iso_alpha_2', 2)->nullable()->after('name');
            }
        });

        if (Schema::hasTable('countries') && DB::table('countries')->count() === 0) {
            DB::table('countries')->insert([
                'name' => 'Portugal',
                'iso_alpha_2' => 'PT',
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        Schema::create('entities', function (Blueprint $table) {
            $table->id();
            $table->unsignedInteger('number')->unique();
            $table->boolean('is_client')->default(false)->index();
            $table->boolean('is_supplier')->default(false)->index();
            $table->string('nif_hash', 64)->nullable()->unique();
            $table->text('nif_encrypted')->nullable();
            $table->string('name');
            $table->text('address_encrypted')->nullable();
            $table->string('postal_code', 16)->nullable();
            $table->string('city')->nullable();
            $table->foreignId('country_id')->nullable()->constrained()->nullOnDelete();
            $table->text('phone_encrypted')->nullable();
            $table->text('mobile_encrypted')->nullable();
            $table->string('website')->nullable();
            $table->string('email')->nullable();
            $table->boolean('gdpr_consent')->nullable();
            $table->text('notes')->nullable();
            $table->boolean('active')->default(true);
            $table->timestamps();
            $table->softDeletes();
        });

        $clientIdToEntityId = [];
        $supplierIdToEntityId = [];
        $nextNumber = 1;

        if (Schema::hasTable('clients')) {
            $clients = DB::table('clients')->orderBy('id')->get();
            foreach ($clients as $row) {
                $nifHash = null;
                $nifEncrypted = $row->nif_encrypted;
                if ($nifEncrypted) {
                    try {
                        $plain = decrypt($nifEncrypted);
                        $nifHash = hash('sha256', strtoupper(preg_replace('/\s+/', '', $plain)));
                    } catch (Throwable) {
                        $nifHash = hash('sha256', 'client-'.$row->id);
                    }
                }
                $baseHash = $nifHash;
                $suffix = 0;
                while ($nifHash && DB::table('entities')->where('nif_hash', $nifHash)->exists()) {
                    $suffix++;
                    $nifHash = hash('sha256', ($baseHash ?? '').'-dup-'.$suffix.'-c'.$row->id);
                }

                $entityId = DB::table('entities')->insertGetId([
                    'number' => $nextNumber++,
                    'is_client' => true,
                    'is_supplier' => false,
                    'nif_hash' => $nifHash,
                    'nif_encrypted' => $nifEncrypted,
                    'name' => $row->name,
                    'address_encrypted' => $row->address_encrypted,
                    'postal_code' => null,
                    'city' => null,
                    'country_id' => null,
                    'phone_encrypted' => $row->phone ? encrypt($row->phone) : null,
                    'mobile_encrypted' => null,
                    'website' => null,
                    'email' => $row->email,
                    'gdpr_consent' => null,
                    'notes' => null,
                    'active' => (bool) $row->active,
                    'created_at' => $row->created_at,
                    'updated_at' => $row->updated_at,
                    'deleted_at' => $row->deleted_at,
                ]);
                $clientIdToEntityId[$row->id] = $entityId;
            }
        }

        if (Schema::hasTable('suppliers')) {
            $suppliers = DB::table('suppliers')->orderBy('id')->get();
            foreach ($suppliers as $row) {
                $nifHash = null;
                $nifEncrypted = $row->nif_encrypted;
                if ($nifEncrypted) {
                    try {
                        $plain = decrypt($nifEncrypted);
                        $nifHash = hash('sha256', strtoupper(preg_replace('/\s+/', '', $plain)));
                    } catch (Throwable) {
                        $nifHash = hash('sha256', 'supplier-'.$row->id);
                    }
                }
                $baseHash = $nifHash;
                $suffix = 0;
                while ($nifHash && DB::table('entities')->where('nif_hash', $nifHash)->exists()) {
                    $suffix++;
                    $nifHash = hash('sha256', ($baseHash ?? '').'-dup-'.$suffix.'-s'.$row->id);
                }

                $entityId = DB::table('entities')->insertGetId([
                    'number' => $nextNumber++,
                    'is_client' => false,
                    'is_supplier' => true,
                    'nif_hash' => $nifHash,
                    'nif_encrypted' => $nifEncrypted,
                    'name' => $row->name,
                    'address_encrypted' => $row->address_encrypted,
                    'postal_code' => null,
                    'city' => null,
                    'country_id' => null,
                    'phone_encrypted' => $row->phone ? encrypt($row->phone) : null,
                    'mobile_encrypted' => null,
                    'website' => null,
                    'email' => $row->email,
                    'gdpr_consent' => null,
                    'notes' => null,
                    'active' => (bool) $row->active,
                    'created_at' => $row->created_at,
                    'updated_at' => $row->updated_at,
                    'deleted_at' => $row->deleted_at,
                ]);
                $supplierIdToEntityId[$row->id] = $entityId;
            }
        }

        if (Schema::hasTable('supplier_invoices') && Schema::hasColumn('supplier_invoices', 'supplier_id')) {
            Schema::table('supplier_invoices', function (Blueprint $table) {
                $table->foreignId('entity_id')->nullable()->after('id')->constrained('entities')->nullOnDelete();
            });

            foreach ($supplierIdToEntityId as $oldSupplierId => $entityId) {
                DB::table('supplier_invoices')
                    ->where('supplier_id', $oldSupplierId)
                    ->update(['entity_id' => $entityId]);
            }

            Schema::table('supplier_invoices', function (Blueprint $table) {
                $table->dropForeign(['supplier_id']);
                $table->dropColumn('supplier_id');
            });
        }

        if (Schema::hasTable('contacts')) {
            foreach ($clientIdToEntityId as $oldId => $entityId) {
                DB::table('contacts')
                    ->where('contactable_type', 'App\\Models\\Client')
                    ->where('contactable_id', $oldId)
                    ->update([
                        'contactable_type' => 'App\\Models\\Entity',
                        'contactable_id' => $entityId,
                    ]);
            }
            foreach ($supplierIdToEntityId as $oldId => $entityId) {
                DB::table('contacts')
                    ->where('contactable_type', 'App\\Models\\Supplier')
                    ->where('contactable_id', $oldId)
                    ->update([
                        'contactable_type' => 'App\\Models\\Entity',
                        'contactable_id' => $entityId,
                    ]);
            }
        }

        if (Schema::hasTable('clients')) {
            Schema::drop('clients');
        }
        if (Schema::hasTable('suppliers')) {
            Schema::drop('suppliers');
        }
    }

    public function down(): void
    {
        throw new RuntimeException('This migration cannot be reversed safely.');
    }
};
