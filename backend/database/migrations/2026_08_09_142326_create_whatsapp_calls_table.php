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
        Schema::create('whatsapp_calls', function (Blueprint $table) {
            $table->id();
            $table->uuid()->unique();
            $table->foreignId('company_id')->nullable()->constrained()->cascadeOnDelete();
            $table->foreignId('whatsapp_call_flow_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('contact_id')->constrained()->cascadeOnDelete();
            $table->foreignId('conversation_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('campaign_recipient_id')->nullable()->constrained()->nullOnDelete();
            $table->enum('direction', ['outbound', 'inbound']);
            $table->enum('status', [
                'ringing', 'in_progress', 'completed', 'failed', 'missed', 'needs_human_followup',
            ])->default('ringing');
            $table->string('meta_call_id')->nullable();
            $table->json('transcript')->nullable();
            $table->json('collected_variables')->nullable();
            $table->boolean('needs_human_followup')->default(false);
            $table->foreignId('human_followup_assigned_to')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('human_followup_completed_at')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('ended_at')->nullable();
            $table->timestamps();

            $table->index('meta_call_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('whatsapp_calls');
    }
};
