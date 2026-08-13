<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private const TABLES = [
        'contacts',
        'conversations',
        'automation_flows',
        'campaigns',
        'daily_metrics',
        'activity_logs',
        'api_connections',
        'notification_preferences',
        'ai_assistant_settings',
        'whatsapp_templates',
        'whatsapp_flows',
        'pipeline_stages',
        'tags',
    ];

    /**
     * Run the migrations.
     */
    public function up(): void
    {
        foreach (self::TABLES as $tableName) {
            Schema::table($tableName, function (Blueprint $table) {
                $table->foreignId('company_id')->nullable()->after('id')
                    ->constrained()->cascadeOnDelete();
            });
        }

        Schema::table('tags', function (Blueprint $table) {
            $table->dropUnique(['name']);
            $table->unique(['company_id', 'name']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('tags', function (Blueprint $table) {
            $table->dropUnique(['company_id', 'name']);
            $table->unique('name');
        });

        foreach (self::TABLES as $tableName) {
            Schema::table($tableName, function (Blueprint $table) {
                $table->dropConstrainedForeignId('company_id');
            });
        }
    }
};
