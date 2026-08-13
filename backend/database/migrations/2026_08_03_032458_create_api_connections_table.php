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
        Schema::create('api_connections', function (Blueprint $table) {
            $table->id();
            $table->uuid()->unique();
            $table->enum('channel', ['whatsapp', 'instagram']);
            $table->string('label');
            $table->string('account_name');
            $table->string('identifier');
            $table->enum('status', ['connected', 'disconnected'])->default('disconnected');
            $table->text('access_token')->nullable();
            $table->timestamp('connected_at')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('api_connections');
    }
};
