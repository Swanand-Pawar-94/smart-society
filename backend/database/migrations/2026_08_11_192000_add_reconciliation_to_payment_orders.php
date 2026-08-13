<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('payment_orders', function (Blueprint $table): void {
            $table->text('verification_note')->nullable()->after('gateway_payment_id');
        });

        Schema::table('maintenance_payments', function (Blueprint $table): void {
            $table->foreignId('payment_order_id')
                ->nullable()
                ->after('id')
                ->constrained()
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('maintenance_payments', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('payment_order_id');
        });

        Schema::table('payment_orders', function (Blueprint $table): void {
            $table->dropColumn('verification_note');
        });
    }
};
