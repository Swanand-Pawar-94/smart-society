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
        Schema::create('resident_transaction_ledger', function (Blueprint $table) {
            $table->id();
            $table->foreignId('resident_id')->constrained()->cascadeOnDelete();
            $table->foreignId('flat_id')->constrained()->cascadeOnDelete();
            $table->enum('transaction_type', ['DEBIT', 'CREDIT'])->index();
            $table->decimal('amount', 12, 2);
            $table->string('description', 255);
            $table->string('category', 100)->index(); // e.g., 'maintenance', 'water', 'electricity', 'parking', 'late_fee', 'payment'
            $table->string('reference_type', 100)->nullable(); // e.g., 'MaintenanceBill', 'MaintenancePayment', 'PaymentOrder'
            $table->unsignedBigInteger('reference_id')->nullable();
            $table->decimal('running_balance', 12, 2)->default(0);
            $table->timestamp('transaction_date')->index();
            $table->timestamps();

            // Indexes for performance
            $table->index(['resident_id', 'transaction_date']);
            $table->index(['flat_id', 'transaction_date']);
            $table->index(['reference_type', 'reference_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('resident_transaction_ledger');
    }
};
