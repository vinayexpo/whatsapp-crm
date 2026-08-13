<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
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
            // SQLite has no ALTER COLUMN; Laravel's enum() on sqlite is backed by a
            // CHECK constraint baked into the CREATE TABLE statement stored in
            // sqlite_master. There's no doctrine/dbal installed in this project to
            // do the usual "rebuild the table" trick automatically, so we patch the
            // stored CREATE TABLE SQL directly (a well-known, safe sqlite technique)
            // and bump the schema version so the connection picks up the change.
            $schemaVersion = (int) DB::selectOne('PRAGMA schema_version')->schema_version;

            DB::statement('PRAGMA writable_schema = 1');
            DB::statement(
                "UPDATE sqlite_master SET sql = REPLACE(sql, \"'whatsapp','instagram'\", \"'whatsapp','instagram','website'\") WHERE name = 'conversations' AND type = 'table'"
            );
            DB::statement('PRAGMA schema_version = '.($schemaVersion + 1));
            DB::statement('PRAGMA writable_schema = 0');
        } else {
            DB::statement("ALTER TABLE conversations MODIFY channel ENUM('whatsapp', 'instagram', 'website') NOT NULL");
        }

        Schema::table('conversations', function (Blueprint $table) {
            $table->foreignId('chatbot_id')->nullable()->after('contact_id')->constrained()->cascadeOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('conversations', function (Blueprint $table) {
            $table->dropConstrainedForeignId('chatbot_id');
        });

        $driver = Schema::getConnection()->getDriverName();

        if ($driver !== 'sqlite') {
            DB::statement("ALTER TABLE conversations MODIFY channel ENUM('whatsapp', 'instagram') NOT NULL");
        }
    }
};
