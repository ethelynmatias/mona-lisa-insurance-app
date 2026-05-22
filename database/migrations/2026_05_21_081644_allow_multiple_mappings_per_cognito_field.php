<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('form_field_mappings', function (Blueprint $table) {
            // Drop the single-mapping-per-field constraint so one Cognito field
            // can map to multiple NowCerts entity/field combinations.
            $table->dropUnique(['form_id', 'cognito_field']);
        });
    }

    public function down(): void
    {
        // Before restoring the unique constraint, remove duplicate rows
        // (keep only the first mapping per cognito_field per form).
        \DB::statement('
            DELETE FROM form_field_mappings
            WHERE id NOT IN (
                SELECT MIN(id)
                FROM form_field_mappings
                GROUP BY form_id, cognito_field
            )
        ');

        Schema::table('form_field_mappings', function (Blueprint $table) {
            $table->unique(['form_id', 'cognito_field']);
        });
    }
};
