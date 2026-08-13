<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('amenities', function (Blueprint $table): void {
            $table->id();
            $table->string('name')->unique();
            $table->text('description')->nullable();
            $table->boolean('is_active')->default(true)->index();
            $table->unsignedSmallInteger('max_booking_hours')->default(2);
            $table->time('opening_time')->nullable();
            $table->time('closing_time')->nullable();
            $table->timestamps();
        });

        Schema::create('amenity_bookings', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('amenity_id')->constrained()->cascadeOnDelete();
            $table->foreignId('resident_id')->constrained()->cascadeOnDelete();
            $table->foreignId('flat_id')->constrained()->cascadeOnDelete();
            $table->date('booking_date')->index();
            $table->time('start_time');
            $table->time('end_time');
            $table->string('status', 30)->default('CONFIRMED')->index();
            $table->timestamp('cancelled_at')->nullable();
            $table->string('notes', 500)->nullable();
            $table->timestamps();
            $table->index(['amenity_id', 'booking_date', 'status']);
            $table->index(['resident_id', 'booking_date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('amenity_bookings');
        Schema::dropIfExists('amenities');
    }
};
