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
        Schema::create('messages', function (Blueprint $table) {
            $table->id();
            $table->uuid()->unique();
            $table->foreignId('conversation_id')->constrained()->cascadeOnDelete();
            $table->enum('direction', ['inbound', 'outbound']);
            $table->text('text');
            $table->enum('status', ['sent', 'delivered', 'read', 'failed'])->default('sent');
            $table->string('external_message_id')->nullable()->unique();
            $table->string('attachment_url')->nullable();
            $table->enum('attachment_type', ['image', 'document'])->nullable();
            $table->timestamp('sent_at');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('messages');
    }
};
