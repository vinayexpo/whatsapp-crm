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
        Schema::table('whatsapp_calls', function (Blueprint $table) {
            $table->text('local_sdp_offer')->nullable();
            $table->text('remote_sdp_answer')->nullable();
            $table->enum('sdp_exchange_status', [
                'pending_offer', 'offer_sent', 'answer_received', 'connected', 'failed',
            ])->default('pending_offer');
            $table->json('remote_ice_candidates')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('whatsapp_calls', function (Blueprint $table) {
            $table->dropColumn(['local_sdp_offer', 'remote_sdp_answer', 'sdp_exchange_status', 'remote_ice_candidates']);
        });
    }
};
