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
        Schema::table('paid_ad_accounts', fn (Blueprint $table) => $table->boolean('auto_sync')->default(true));
        Schema::table('paid_ad_campaigns', function (Blueprint $table) {
            $table->json('meta_snapshot')->nullable();
            $table->json('pending_changes')->nullable();
            $table->json('sync_conflicts')->nullable();
            $table->string('sync_state')->default('NOT_SYNCED');
            $table->text('sync_error')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('paid_ad_campaigns', fn (Blueprint $table) => $table->dropColumn(['meta_snapshot', 'pending_changes', 'sync_conflicts', 'sync_state', 'sync_error']));
        Schema::table('paid_ad_accounts', fn (Blueprint $table) => $table->dropColumn('auto_sync'));
    }
};
