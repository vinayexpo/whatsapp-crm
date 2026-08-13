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
        Schema::create('notification_preferences', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->unique()->constrained()->cascadeOnDelete();
            $table->boolean('new_message_alerts')->default(true);
            $table->boolean('campaign_completed')->default(true);
            $table->boolean('automation_triggered')->default(false);
            $table->boolean('daily_summary_email')->default(true);
            $table->boolean('weekly_analytics_report')->default(true);
            $table->boolean('sound_alerts')->default(false);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('notification_preferences');
    }
};
