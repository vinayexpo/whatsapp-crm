<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private const OLD_TYPES = "'new_message', 'campaign_completed', 'automation_triggered', 'whatsapp_call_missed', 'whatsapp_call_completed', 'template_approved', 'template_rejected'";

    private const NEW_TYPES = "'new_message', 'campaign_completed', 'campaign_failed', 'automation_triggered', 'whatsapp_call_missed', 'whatsapp_call_completed', 'whatsapp_call_ringing', 'voice_call_completed', 'voice_call_missed', 'voice_call_ringing', 'template_approved', 'template_rejected', 'chatbot_handoff'";

    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('notification_preferences', function (Blueprint $table) {
            $table->boolean('voice_call_alerts')->default(true)->after('whatsapp_call_alerts');
        });

        $driver = Schema::getConnection()->getDriverName();

        if ($driver === 'sqlite') {
            $schemaVersion = (int) DB::selectOne('PRAGMA schema_version')->schema_version;

            DB::statement('PRAGMA writable_schema = 1');
            DB::statement(
                'UPDATE sqlite_master SET sql = REPLACE(sql, "'.self::OLD_TYPES.'", "'.self::NEW_TYPES.'") WHERE name = \'notifications\' AND type = \'table\''
            );
            DB::statement('PRAGMA schema_version = '.($schemaVersion + 1));
            DB::statement('PRAGMA writable_schema = 0');
        } else {
            DB::statement("ALTER TABLE notifications MODIFY type ENUM(".self::NEW_TYPES.") NOT NULL");
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        $driver = Schema::getConnection()->getDriverName();

        if ($driver === 'sqlite') {
            $schemaVersion = (int) DB::selectOne('PRAGMA schema_version')->schema_version;

            DB::statement('PRAGMA writable_schema = 1');
            DB::statement(
                'UPDATE sqlite_master SET sql = REPLACE(sql, "'.self::NEW_TYPES.'", "'.self::OLD_TYPES.'") WHERE name = \'notifications\' AND type = \'table\''
            );
            DB::statement('PRAGMA schema_version = '.($schemaVersion + 1));
            DB::statement('PRAGMA writable_schema = 0');
        } else {
            DB::statement("ALTER TABLE notifications MODIFY type ENUM(".self::OLD_TYPES.") NOT NULL");
        }

        Schema::table('notification_preferences', function (Blueprint $table) {
            $table->dropColumn('voice_call_alerts');
        });
    }
};
