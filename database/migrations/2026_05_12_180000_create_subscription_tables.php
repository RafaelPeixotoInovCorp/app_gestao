<?php

use App\Models\Tenant;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('notifications')) {
            Schema::create('notifications', function (Blueprint $table) {
                $table->uuid('id')->primary();
                $table->string('type');
                $table->morphs('notifiable');
                $table->text('data');
                $table->timestamp('read_at')->nullable();
                $table->timestamps();
            });
        }

        Schema::create('plans', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('slug')->unique();
            $table->text('description')->nullable();
            $table->decimal('price_per_month', 12, 2)->default(0);
            $table->unsignedSmallInteger('max_users')->nullable()->comment('null = ilimitado');
            $table->unsignedSmallInteger('trial_days')->default(0);
            $table->boolean('has_premium_modules')->default(false);
            $table->boolean('is_public')->default(true);
            $table->unsignedTinyInteger('sort_order')->default(0);
            $table->timestamps();
        });

        Schema::create('tenant_subscriptions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('plan_id')->constrained('plans')->restrictOnDelete();
            $table->string('status', 24)->default('trialing');
            $table->timestamp('trial_ends_at')->nullable();
            $table->date('current_period_start');
            $table->date('current_period_end');
            $table->boolean('cancel_at_period_end')->default(false);
            $table->timestamp('canceled_at')->nullable();
            $table->foreignId('pending_plan_id')->nullable()->constrained('plans')->nullOnDelete();
            $table->decimal('pending_cycle_credit', 12, 2)->default(0);
            $table->json('trial_reminders_sent')->nullable();
            $table->timestamps();

            $table->unique('tenant_id');
        });

        Schema::create('billing_ledger_entries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('tenant_subscription_id')->nullable()->constrained('tenant_subscriptions')->nullOnDelete();
            $table->string('entry_type', 48);
            $table->decimal('amount', 14, 4);
            $table->char('currency', 3)->default('EUR');
            $table->string('description');
            $table->json('metadata')->nullable();
            $table->timestamps();
        });

        Schema::create('subscription_audit_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('actor_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('action', 48);
            $table->foreignId('from_plan_id')->nullable()->constrained('plans')->nullOnDelete();
            $table->foreignId('to_plan_id')->nullable()->constrained('plans')->nullOnDelete();
            $table->json('metadata')->nullable();
            $table->timestamps();
        });

        $this->seedPlans();

        $defaultPlanId = DB::table('plans')->where('slug', 'basico')->value('id');
        if ($defaultPlanId) {
            foreach (Tenant::query()->cursor() as $tenant) {
                if (DB::table('tenant_subscriptions')->where('tenant_id', $tenant->id)->exists()) {
                    continue;
                }
                $start = now()->startOfDay();
                DB::table('tenant_subscriptions')->insert([
                    'tenant_id' => $tenant->id,
                    'plan_id' => $defaultPlanId,
                    'status' => 'active',
                    'trial_ends_at' => null,
                    'current_period_start' => $start->toDateString(),
                    'current_period_end' => $start->copy()->addYear()->toDateString(),
                    'cancel_at_period_end' => false,
                    'canceled_at' => null,
                    'pending_plan_id' => null,
                    'pending_cycle_credit' => 0,
                    'trial_reminders_sent' => null,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
        }
    }

    private function seedPlans(): void
    {
        $rows = [
            [
                'name' => 'Plano Básico',
                'slug' => 'basico',
                'description' => 'Ideal para equipas pequenas. Sem módulos financeiros premium.',
                'price_per_month' => 0,
                'max_users' => 3,
                'trial_days' => 0,
                'has_premium_modules' => false,
                'is_public' => true,
                'sort_order' => 1,
            ],
            [
                'name' => 'Plano Profissional',
                'slug' => 'profissional',
                'description' => 'Operações completas com limite de utilizadores e trial incluído.',
                'price_per_month' => 49,
                'max_users' => 15,
                'trial_days' => 14,
                'has_premium_modules' => true,
                'is_public' => true,
                'sort_order' => 2,
            ],
            [
                'name' => 'Plano Empresarial',
                'slug' => 'empresarial',
                'description' => 'Utilizadores ilimitados e todos os módulos premium.',
                'price_per_month' => 149,
                'max_users' => null,
                'trial_days' => 14,
                'has_premium_modules' => true,
                'is_public' => true,
                'sort_order' => 3,
            ],
        ];

        $ts = now();
        foreach ($rows as $row) {
            DB::table('plans')->updateOrInsert(
                ['slug' => $row['slug']],
                array_merge($row, ['created_at' => $ts, 'updated_at' => $ts])
            );
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('subscription_audit_logs');
        Schema::dropIfExists('billing_ledger_entries');
        Schema::dropIfExists('tenant_subscriptions');
        Schema::dropIfExists('plans');
    }
};
