<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('notices', function (Blueprint $table): void {
            $table->string('category', 30)->default('GENERAL')->after('content');
            $table->string('priority', 30)->default('NORMAL')->after('category');
            $table->timestamp('expires_at')->nullable()->index()->after('published_at');
            $table->string('attachment_path')->nullable()->after('expires_at');
        });
    }

    public function down(): void
    {
        Schema::table('notices', function (Blueprint $table): void {
            $table->dropIndex(['expires_at']);
            $table->dropColumn(['category', 'priority', 'expires_at', 'attachment_path']);
        });
    }
};
