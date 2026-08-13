<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('maintenance_bills', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('flat_id')->constrained()->cascadeOnDelete();
            $table->date('billing_month');
            $table->date('due_date')->index();
            $table->decimal('amount', 12, 2);
            $table->string('status', 30)->default('UNPAID')->index();
            $table->string('notes', 500)->nullable();
            $table->timestamps();
            $table->unique(['flat_id', 'billing_month']);
        });

        Schema::create('maintenance_payments', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('maintenance_bill_id')->constrained()->cascadeOnDelete();
            $table->foreignId('resident_id')->constrained()->cascadeOnDelete();
            $table->decimal('amount', 12, 2);
            $table->string('reference_id', 100)->nullable()->unique();
            $table->string('payment_method', 30);
            $table->string('status', 30)->default('PENDING')->index();
            $table->timestamp('paid_at')->nullable();
            $table->string('receipt_reference', 150)->nullable();
            $table->timestamps();
            $table->index(['resident_id', 'created_at']);
            $table->index(['maintenance_bill_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('maintenance_payments');
        Schema::dropIfExists('maintenance_bills');
    }
};
