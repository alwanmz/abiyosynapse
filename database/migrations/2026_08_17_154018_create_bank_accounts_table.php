<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('bank_accounts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->string('code');
            $table->string('name');
            // 'cash' -> child of GL account 1.1.1 Kas; 'bank' -> child of
            // 1.1.2 Bank. Each BankAccount owns its own leaf GL account
            // (auto-created by BankAccountService) rather than every bank
            // account sharing the single 1.1.1/1.1.2 leaf, so per-bank
            // balances are directly readable from the chart of accounts.
            $table->enum('type', ['cash', 'bank']);
            $table->string('bank_name')->nullable();
            $table->string('account_number')->nullable();
            $table->foreignId('account_id')->constrained('accounts')->restrictOnDelete();
            $table->decimal('opening_balance', 18, 2)->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->unique(['company_id', 'code']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('bank_accounts');
    }
};
