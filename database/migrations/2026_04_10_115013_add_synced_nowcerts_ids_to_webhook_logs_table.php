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
        Schema::table('webhook_logs', function (Blueprint $table) {
            // Stores NowCerts DatabaseIds resolved after first sync so reruns can inject them directly
            $table->json('synced_nowcerts_ids')->nullable()->after('synced_at');
        });
    }

    public function down(): void
    {
        Schema::table('webhook_logs', function (Blueprint $table) {
            $table->dropColumn('synced_nowcerts_ids');
        });
    }
};
