<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payment_orders', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('resident_id')->constrained()->cascadeOnDelete();
            $table->foreignId('flat_id')->constrained()->cascadeOnDelete();
            $table->decimal('amount', 12, 2);
            $table->string('payment_method', 30);
            $table->string('status', 30)->default('PENDING')->index();
            $table->string('provider', 50);
            $table->string('provider_reference', 100)->unique();
            $table->string('gateway_payment_id', 150)->nullable()->unique();
            $table->timestamp('verified_at')->nullable();
            $table->timestamps();
            $table->index(['resident_id', 'status']);
        });

        Schema::create('payment_order_items', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('payment_order_id')->constrained()->cascadeOnDelete();
            $table->foreignId('maintenance_bill_id')->constrained()->cascadeOnDelete();
            $table->decimal('amount', 12, 2);
            $table->timestamps();
            $table->unique(['payment_order_id', 'maintenance_bill_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payment_order_items');
        Schema::dropIfExists('payment_orders');
    }
};
