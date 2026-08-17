<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('journal_entries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->string('number');
            $table->date('entry_date');
            $table->text('description')->nullable();
            // Polymorphic link back to whatever business event created this
            // entry (PurchaseReceipt, SalesInvoice, ProductionOrder, or null
            // for a manual "Jurnal Umum" entry). Nullable on purpose — not
            // every entry originates from another module.
            $table->nullableMorphs('sourceable');
            $table->enum('status', ['draft', 'posted', 'void'])->default('draft');
            $table->decimal('total_debit', 18, 2)->default(0);
            $table->decimal('total_credit', 18, 2)->default(0);
            $table->timestamp('posted_at')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['company_id', 'number']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('journal_entries');
    }
};
