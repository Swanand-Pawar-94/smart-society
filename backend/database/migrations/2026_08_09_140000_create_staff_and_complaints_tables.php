<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('staff_members', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->nullable()->unique()->constrained()->nullOnDelete();
            $table->string('name');
            $table->string('mobile', 20);
            $table->string('email')->nullable();
            $table->string('designation', 100);
            $table->string('status', 30)->default('ACTIVE')->index();
            $table->date('joining_date');
            $table->timestamps();
        });

        Schema::create('complaints', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('resident_id')->constrained()->cascadeOnDelete();
            $table->foreignId('flat_id')->constrained()->cascadeOnDelete();
            $table->foreignId('assigned_staff_id')->nullable()->constrained('staff_members')->nullOnDelete();
            $table->string('category', 100);
            $table->string('title');
            $table->text('description');
            $table->string('priority', 30)->default('MEDIUM')->index();
            $table->string('status', 30)->default('OPEN')->index();
            $table->timestamp('resolved_at')->nullable();
            $table->timestamp('closed_at')->nullable();
            $table->timestamps();
            $table->index(['flat_id', 'status']);
            $table->index(['resident_id', 'created_at']);
        });

        Schema::create('complaint_status_histories', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('complaint_id')->constrained()->cascadeOnDelete();
            $table->string('from_status', 30)->nullable();
            $table->string('to_status', 30);
            $table->foreignId('changed_by_user_id')->constrained('users')->cascadeOnDelete();
            $table->string('note', 500)->nullable();
            $table->timestamps();
            $table->index(['complaint_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('complaint_status_histories');
        Schema::dropIfExists('complaints');
        Schema::dropIfExists('staff_members');
    }
};
