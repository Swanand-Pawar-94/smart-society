<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('user_profiles', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->unique()->constrained()->cascadeOnDelete();
            $table->date('date_of_birth')->nullable();
            $table->string('gender', 30)->nullable();
            $table->string('emergency_contact', 25)->nullable();
            $table->string('profile_photo_path')->nullable();
            $table->timestamps();
        });

        Schema::table('staff_members', function (Blueprint $table): void {
            $table->string('employee_id', 50)->nullable()->unique()->after('user_id');
            $table->string('shift', 50)->nullable()->after('designation');
            $table->string('emergency_contact', 25)->nullable()->after('joining_date');
        });
    }

    public function down(): void
    {
        Schema::table('staff_members', function (Blueprint $table): void {
            $table->dropUnique(['employee_id']);
            $table->dropColumn(['employee_id', 'shift', 'emergency_contact']);
        });

        Schema::dropIfExists('user_profiles');
    }
};
