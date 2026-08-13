<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('visitors', function (Blueprint $table): void {
            $table->id();
            $table->string('visitor_name');
            $table->string('mobile_number', 25);
            $table->string('purpose', 255);
            $table->foreignId('flat_id')->constrained()->cascadeOnDelete();
            $table->foreignId('resident_id')->constrained()->cascadeOnDelete();
            $table->string('vehicle_number', 30)->nullable();
            $table->string('visitor_type', 30)->default('GUEST')->index();
            $table->string('entry_status', 30)->default('WAITING')->index();
            $table->string('approval_status', 30)->default('PENDING')->index();
            $table->timestamp('expected_at')->nullable();
            $table->timestamp('entered_at')->nullable();
            $table->timestamp('exited_at')->nullable();
            $table->timestamp('approved_at')->nullable();
            $table->string('approval_note', 500)->nullable();
            $table->boolean('is_pre_approved')->default(false)->index();
            $table->foreignId('created_by_security_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('approved_by_resident_id')->nullable()->constrained('residents')->nullOnDelete();
            $table->timestamps();

            $table->index(['flat_id', 'approval_status']);
            $table->index(['resident_id', 'approval_status']);
            $table->index(['entry_status', 'created_at']);
            $table->index('mobile_number');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('visitors');
    }
};
