<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('webhook_logs', function (Blueprint $table) {
            $table->string('sync_status')->default('pending')->after('status'); // pending | synced | failed | skipped
            $table->text('sync_error')->nullable()->after('sync_status');
            $table->json('synced_entities')->nullable()->after('sync_error'); // e.g. ['Insured','Policy']
            $table->timestamp('synced_at')->nullable()->after('synced_entities');
        });
    }

    public function down(): void
    {
        Schema::table('webhook_logs', function (Blueprint $table) {
            $table->dropColumn(['sync_status', 'sync_error', 'synced_entities', 'synced_at']);
        });
    }
};
