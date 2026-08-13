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
        Schema::create('daily_metrics', function (Blueprint $table) {
            $table->id();
            $table->date('date')->unique();
            $table->unsignedInteger('whatsapp_sent')->default(0);
            $table->unsignedInteger('instagram_sent')->default(0);
            $table->unsignedInteger('delivered')->default(0);
            $table->unsignedInteger('read')->default(0);
            $table->unsignedInteger('replied')->default(0);
            $table->unsignedInteger('avg_response_minutes')->default(0);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('daily_metrics');
    }
};
