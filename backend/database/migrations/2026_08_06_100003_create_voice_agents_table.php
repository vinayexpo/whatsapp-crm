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
        Schema::create('voice_agents', function (Blueprint $table) {
            $table->id();
            $table->uuid()->unique();
            $table->foreignId('company_id')->nullable()->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->enum('status', ['active', 'paused'])->default('paused');
            $table->enum('voice_mode', ['tts', 'recorded'])->default('tts');
            $table->string('tts_voice_id')->nullable();
            $table->string('greeting_audio_url')->nullable();
            $table->json('qualification_criteria');
            $table->text('qualification_pass_criteria')->nullable();
            $table->unsignedTinyInteger('max_call_attempts')->default(1);
            $table->text('system_prompt')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('voice_agents');
    }
};
