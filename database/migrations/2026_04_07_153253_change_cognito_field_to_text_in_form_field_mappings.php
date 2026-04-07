<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('form_field_mappings', function (Blueprint $table) {
            $table->dropUnique(['form_id', 'cognito_field']);
            $table->text('cognito_field')->change();
        });

        // Re-add unique index with prefix length (MySQL TEXT columns require this)
        DB::statement('ALTER TABLE form_field_mappings ADD UNIQUE form_field_mappings_form_id_cognito_field_unique (form_id, cognito_field(191))');
    }

    public function down(): void
    {
        Schema::table('form_field_mappings', function (Blueprint $table) {
            $table->dropUnique('form_field_mappings_form_id_cognito_field_unique');
            $table->string('cognito_field')->change();
            $table->unique(['form_id', 'cognito_field']);
        });
    }
};
