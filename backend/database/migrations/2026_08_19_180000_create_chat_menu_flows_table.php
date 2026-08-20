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
        Schema::create('chat_menu_flows', function (Blueprint $table) {
            $table->id();
            $table->uuid()->unique();
            $table->foreignId('company_id')->nullable()->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->enum('channel', ['whatsapp', 'web', 'both'])->default('both');
            $table->enum('status', ['active', 'paused'])->default('paused');
            $table->string('trigger_keyword')->nullable();
            $table->string('entry_node_id')->nullable();
            $table->json('nodes');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('chat_menu_flows');
    }
};
