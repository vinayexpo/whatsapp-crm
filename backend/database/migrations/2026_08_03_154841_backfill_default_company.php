<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

return new class extends Migration
{
    private const TENANT_TABLES = [
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
        $companyId = DB::table('companies')->first()?->id;

        if ($companyId === null) {
            $companyId = DB::table('companies')->insertGetId([
                'uuid' => (string) Str::uuid(),
                'name' => 'Default Company',
                'status' => 'active',
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        foreach (self::TENANT_TABLES as $tableName) {
            DB::table($tableName)->whereNull('company_id')->update(['company_id' => $companyId]);
        }

        DB::table('users')->whereNull('company_id')->update(['company_id' => $companyId]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Irreversible data backfill; no-op.
    }
};
