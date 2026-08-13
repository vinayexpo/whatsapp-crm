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
        Schema::create('whatsapp_templates', function (Blueprint $table) {
            $table->id();
            $table->uuid()->unique();
            $table->foreignId('api_connection_id')->constrained()->cascadeOnDelete();
            $table->string('meta_template_id');
            $table->string('name');
            $table->string('language');
            $table->string('category');
            $table->enum('status', ['approved', 'pending', 'rejected'])->default('pending');
            $table->text('body');
            $table->json('variables')->nullable();
            $table->timestamp('synced_at');
            $table->timestamps();

            $table->unique(['api_connection_id', 'meta_template_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('whatsapp_templates');
    }
};
