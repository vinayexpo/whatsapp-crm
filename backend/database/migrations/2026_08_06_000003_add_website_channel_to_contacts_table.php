<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        $driver = Schema::getConnection()->getDriverName();

        if ($driver === 'sqlite') {
            // See 2026_08_06_000002_add_website_channel_and_chatbot_id_to_conversations_table
            // for why this direct sqlite_master patch is used instead of a table rebuild.
            $schemaVersion = (int) DB::selectOne('PRAGMA schema_version')->schema_version;

            DB::statement('PRAGMA writable_schema = 1');
            DB::statement(
                "UPDATE sqlite_master SET sql = REPLACE(sql, \"'whatsapp','instagram'\", \"'whatsapp','instagram','website'\") WHERE name = 'contacts' AND type = 'table'"
            );
            DB::statement('PRAGMA schema_version = '.($schemaVersion + 1));
            DB::statement('PRAGMA writable_schema = 0');
        } else {
            DB::statement("ALTER TABLE contacts MODIFY channel ENUM('whatsapp', 'instagram', 'website') NOT NULL");
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        $driver = Schema::getConnection()->getDriverName();

        if ($driver !== 'sqlite') {
            DB::statement("ALTER TABLE contacts MODIFY channel ENUM('whatsapp', 'instagram') NOT NULL");
        }
    }
};
