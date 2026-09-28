<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('subscription_plans', function (Blueprint $table) {
            $table->decimal('price_yearly', 18, 2)->nullable()->after('price');
            $table->decimal('price_lifetime', 18, 2)->nullable()->after('price_yearly');
            $table->boolean('is_purchasable')->default(false)->after('is_active');
        });

        // Yearly = 10x monthly, lifetime = 30x monthly.
        foreach (['growth' => 1490000, 'enterprise' => 4990000] as $code => $monthly) {
            DB::table('subscription_plans')->where('code', $code)->update([
                'price_yearly' => $monthly * 10,
                'price_lifetime' => $monthly * 30,
                'is_purchasable' => true,
            ]);
        }

        Schema::table('company_subscriptions', function (Blueprint $table) {
            // Null while the subscription has never been paid (trial).
            $table->string('billing_cycle', 20)->nullable()->after('status');
        });

        Schema::create('subscription_orders', function (Blueprint $table) {
            $table->id();
            $table->string('number', 40)->unique();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('subscription_plan_id')->constrained()->restrictOnDelete();
            $table->string('billing_cycle', 20);
            $table->decimal('amount', 18, 2);
            $table->string('currency_code', 3);
            $table->string('status', 20)->default('pending');
            $table->string('provider', 30);
            $table->string('provider_ref')->nullable();
            $table->text('checkout_url')->nullable();
            $table->timestamp('paid_at')->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->json('payload')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['company_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('subscription_orders');

        Schema::table('company_subscriptions', function (Blueprint $table) {
            $table->dropColumn('billing_cycle');
        });

        Schema::table('subscription_plans', function (Blueprint $table) {
            $table->dropColumn(['price_yearly', 'price_lifetime', 'is_purchasable']);
        });
    }
};
