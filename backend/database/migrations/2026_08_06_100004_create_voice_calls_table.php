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
        Schema::create('voice_calls', function (Blueprint $table) {
            $table->id();
            $table->uuid()->unique();
            $table->foreignId('company_id')->nullable()->constrained()->cascadeOnDelete();
            $table->foreignId('voice_agent_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('contact_id')->constrained()->cascadeOnDelete();
            $table->foreignId('campaign_recipient_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('conversation_id')->nullable()->constrained()->nullOnDelete();
            $table->enum('direction', ['outbound', 'inbound']);
            $table->enum('medium', ['telephony', 'browser_simulation']);
            $table->enum('status', [
                'queued', 'ringing', 'in_progress', 'completed', 'failed', 'no_answer', 'needs_human_followup',
            ])->default('queued');
            $table->string('provider_call_sid')->nullable();
            $table->string('recording_url')->nullable();
            $table->json('transcript')->nullable();
            $table->json('qualification_fields')->nullable();
            $table->enum('qualification_outcome', ['qualified', 'not_qualified', 'uncertain'])->nullable();
            $table->text('qualification_summary')->nullable();
            $table->boolean('needs_human_followup')->default(false);
            $table->foreignId('human_followup_assigned_to')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('human_followup_completed_at')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('ended_at')->nullable();
            $table->timestamps();

            $table->index('provider_call_sid');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('voice_calls');
    }
};
