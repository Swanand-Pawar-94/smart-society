<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payment_receipts', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('payment_order_id')->unique()->constrained()->cascadeOnDelete();
            $table->string('receipt_number', 100)->unique();
            $table->decimal('amount', 12, 2);
            $table->string('currency', 3)->default('INR');
            $table->string('payment_method', 30);
            $table->string('payment_reference', 150);
            $table->timestamp('issued_at')->index();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payment_receipts');
    }
};
