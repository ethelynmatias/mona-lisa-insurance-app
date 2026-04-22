<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('app_logs', function (Blueprint $table) {
            $table->id();
            $table->string('level', 20)->index();
            $table->string('channel', 50)->nullable()->index();
            $table->text('message');
            $table->json('context')->nullable();
            $table->timestamp('logged_at')->index();
            $table->string('user_id', 50)->nullable()->index();
            $table->string('session_id', 50)->nullable();
            $table->string('ip_address', 45)->nullable();
            $table->string('user_agent')->nullable();
            $table->string('request_id', 50)->nullable()->index();
            $table->string('form_id', 20)->nullable()->index();
            $table->string('webhook_log_id', 20)->nullable()->index();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('app_logs');
    }
};
