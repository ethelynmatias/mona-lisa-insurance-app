<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('form_field_mappings', function (Blueprint $table) {
            $table->id();
            $table->string('form_id');
            $table->string('cognito_field');
            $table->string('nowcerts_entity')->nullable();
            $table->string('nowcerts_field')->nullable();
            $table->timestamps();

            $table->unique(['form_id', 'cognito_field']);
            $table->index('form_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('form_field_mappings');
    }
};
