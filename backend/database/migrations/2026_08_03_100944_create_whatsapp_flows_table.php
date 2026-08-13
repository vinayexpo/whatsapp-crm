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
        Schema::create('whatsapp_flows', function (Blueprint $table) {
            $table->id();
            $table->uuid()->unique();
            $table->foreignId('api_connection_id')->constrained()->cascadeOnDelete();
            $table->string('meta_flow_id');
            $table->string('name');
            $table->enum('status', ['draft', 'published', 'deprecated'])->default('draft');
            $table->json('categories')->nullable();
            $table->timestamp('synced_at');
            $table->timestamps();

            $table->unique(['api_connection_id', 'meta_flow_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('whatsapp_flows');
    }
};
