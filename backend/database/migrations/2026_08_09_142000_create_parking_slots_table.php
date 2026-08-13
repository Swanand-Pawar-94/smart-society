<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('parking_slots', function (Blueprint $table): void {
            $table->id();
            $table->string('slot_number', 50)->unique();
            $table->string('parking_type', 30)->index();
            $table->string('status', 30)->default('AVAILABLE')->index();
            $table->foreignId('flat_id')->nullable()->constrained()->nullOnDelete();
            $table->string('vehicle_number', 30)->nullable();
            $table->string('vehicle_type', 30)->nullable();
            $table->string('vehicle_description', 150)->nullable();
            $table->timestamps();
            $table->index(['flat_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('parking_slots');
    }
};
