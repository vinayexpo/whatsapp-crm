<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('notification_preferences', function (Blueprint $table) {
            $table->boolean('whatsapp_call_alerts')->default(true)->after('automation_triggered');
            $table->boolean('template_status_alerts')->default(true)->after('whatsapp_call_alerts');
        });
    }

    public function down(): void
    {
        Schema::table('notification_preferences', function (Blueprint $table) {
            $table->dropColumn(['whatsapp_call_alerts', 'template_status_alerts']);
        });
    }
};
