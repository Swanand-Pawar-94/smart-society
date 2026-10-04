<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('resident_transactions', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('resident_id')->constrained()->cascadeOnDelete();
            $table->foreignId('flat_id')->constrained()->cascadeOnDelete();
            $table->enum('transaction_type', ['DEBIT', 'CREDIT'])->index();
            $table->string('category', 50)->index(); // MAINTENANCE, WATER, ELECTRICITY, PARKING, LATE_FEE, PAYMENT, etc.
            $table->string('description', 500);
            $table->decimal('amount', 12, 2);
            $table->decimal('balance_after', 12, 2); // Running balance after this transaction
            $table->date('transaction_date')->index();
            $table->string('reference_type', 50)->nullable(); // MaintenanceBill, MaintenancePayment, etc.
            $table->unsignedBigInteger('reference_id')->nullable(); // ID of the related record
            $table->string('reference_number', 100)->nullable(); // Invoice number, receipt number, etc.
            $table->string('payment_method', 30)->nullable(); // For credit transactions
            $table->string('status', 30)->default('COMPLETED')->index(); // COMPLETED, PENDING, FAILED, CANCELLED
            $table->timestamps();
            
            // Indexes for efficient querying
            $table->index(['resident_id', 'transaction_date']);
            $table->index(['flat_id', 'transaction_date']);
            $table->index(['reference_type', 'reference_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('resident_transactions');
    }
};
