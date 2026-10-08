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
        Schema::table('ai_assistant_settings', function (Blueprint $table) {
            $table->string('stt_model')->default('whisper-1');
            $table->string('tts_model')->default('tts-1');
            $table->string('tts_voice')->default('alloy');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('ai_assistant_settings', function (Blueprint $table) {
            $table->dropColumn(['stt_model', 'tts_model', 'tts_voice']);
        });
    }
};
