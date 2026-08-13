<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('parcels', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('flat_id')->constrained()->cascadeOnDelete();
            $table->foreignId('resident_id')->constrained()->cascadeOnDelete();
            $table->foreignId('received_by_user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('collected_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('courier_name', 150);
            $table->string('tracking_number', 100)->nullable()->index();
            $table->string('parcel_type', 30)->default('OTHER');
            $table->string('status', 30)->default('AWAITING_PICKUP')->index();
            $table->timestamp('received_at')->index();
            $table->timestamp('collected_at')->nullable();
            $table->string('notes', 500)->nullable();
            $table->timestamps();
            $table->index(['resident_id', 'status']);
            $table->index(['flat_id', 'received_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('parcels');
    }
};
