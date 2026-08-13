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
        Schema::create('whatsapp_call_flows', function (Blueprint $table) {
            $table->id();
            $table->uuid()->unique();
            $table->foreignId('company_id')->nullable()->constrained()->cascadeOnDelete();
            $table->foreignId('api_connection_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->enum('status', ['active', 'paused'])->default('paused');
            $table->text('greeting_message');
            $table->json('nodes');
            $table->text('fallback_message')->nullable();
            $table->unsignedTinyInteger('max_retries')->default(2);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('whatsapp_call_flows');
    }
};
