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
        Schema::create('contacts', function (Blueprint $table) {
            $table->id();
            $table->uuid()->unique();
            $table->string('name');
            $table->string('avatar_url')->nullable();
            $table->enum('channel', ['whatsapp', 'instagram']);
            $table->string('handle');
            $table->string('phone')->nullable();
            $table->string('email')->nullable();
            $table->string('location')->nullable();
            $table->string('pipeline_stage_id');
            $table->unsignedBigInteger('deal_value')->default(0);
            $table->timestamp('last_interaction_at')->nullable();
            $table->json('notes')->nullable();
            $table->json('purchases')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->foreign('pipeline_stage_id')->references('id')->on('pipeline_stages');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('contacts');
    }
};
