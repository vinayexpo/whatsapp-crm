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
        Schema::create('chatbot_training_entries', function (Blueprint $table) {
            $table->id();
            $table->uuid()->unique();
            $table->foreignId('chatbot_id')->constrained()->cascadeOnDelete();
            $table->text('question');
            $table->text('answer');
            $table->enum('source', ['manual', 'ai'])->default('manual');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('chatbot_training_entries');
    }
};
