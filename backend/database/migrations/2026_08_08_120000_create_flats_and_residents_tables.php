<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('flats', function (Blueprint $table): void {
            $table->id();
            $table->string('flat_number', 30);
            $table->string('building', 60);
            $table->string('floor', 30)->nullable();
            $table->foreignId('owner_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('occupancy_status', 20)->default('VACANT')->index();
            $table->timestamps();

            $table->unique(['building', 'flat_number']);
        });

        Schema::create('residents', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->unique()->constrained('users')->cascadeOnDelete();
            $table->foreignId('flat_id')->nullable()->constrained('flats')->nullOnDelete();
            $table->string('relation_to_owner', 50)->nullable();
            $table->boolean('is_primary_contact')->default(false)->index();
            $table->timestamps();

            $table->index(['flat_id', 'is_primary_contact']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('residents');
        Schema::dropIfExists('flats');
    }
};
