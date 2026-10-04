<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('user_device_tokens')) {
            Schema::create('user_device_tokens', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
                $table->text('fcm_token');
                $table->string('platform', 20)->default('android');
                $table->string('device_id', 100)->nullable();
                $table->boolean('is_active')->default(true)->index();
                $table->timestamp('last_used_at')->nullable();
                $table->timestamps();

                $table->index(['user_id', 'is_active']);
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('user_device_tokens');
    }
};