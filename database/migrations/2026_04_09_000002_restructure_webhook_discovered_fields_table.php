<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::dropIfExists('webhook_discovered_fields');

        Schema::create('webhook_discovered_fields', function (Blueprint $table) {
            $table->id();
            $table->string('form_id')->unique();
            $table->json('fields')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('webhook_discovered_fields');
    }
};
