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
            $table->string('sidecar_session_id')->nullable();
            $table->enum('answered_by', ['human_agent', 'ai_sidecar'])->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('whatsapp_calls', function (Blueprint $table) {
            $table->dropColumn(['sidecar_session_id', 'answered_by']);
        });
    }
};
