<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('fixed_assets', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->string('number');
            $table->string('name');
            $table->string('category')->nullable();
            $table->foreignId('asset_account_id')->constrained('accounts')->restrictOnDelete();
            $table->foreignId('accumulated_depreciation_account_id')->constrained('accounts')->restrictOnDelete();
            $table->foreignId('depreciation_expense_account_id')->constrained('accounts')->restrictOnDelete();
            $table->foreignId('source_account_id')->constrained('accounts')->restrictOnDelete();
            $table->date('acquisition_date');
            $table->date('placed_in_service_date')->nullable();
            $table->decimal('acquisition_cost', 18, 2);
            $table->decimal('salvage_value', 18, 2)->default(0);
            $table->unsignedInteger('useful_life_months');
            $table->enum('depreciation_method', ['straight_line'])->default('straight_line');
            $table->decimal('accumulated_depreciation', 18, 2)->default(0);
            $table->enum('status', ['draft', 'active', 'fully_depreciated', 'disposed'])->default('draft');
            $table->text('notes')->nullable();
            $table->timestamp('activated_at')->nullable();
            $table->foreignId('activated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('disposed_at')->nullable();
            $table->foreignId('disposed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->decimal('disposal_proceeds', 18, 2)->nullable();
            $table->decimal('disposal_gain_loss', 18, 2)->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['company_id', 'number']);
            $table->index(['company_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('fixed_assets');
    }
};
