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
            $schemaVersion = (int) DB::selectOne('PRAGMA schema_version')->schema_version;

            DB::statement('PRAGMA writable_schema = 1');
            DB::statement(
                "UPDATE sqlite_master SET sql = REPLACE(sql, \"'whatsapp','instagram'\", \"'whatsapp','instagram','voice'\") WHERE name = 'api_connections' AND type = 'table'"
            );
            DB::statement('PRAGMA schema_version = '.($schemaVersion + 1));
            DB::statement('PRAGMA writable_schema = 0');
        } else {
            DB::statement("ALTER TABLE api_connections MODIFY channel ENUM('whatsapp', 'instagram', 'voice') NOT NULL");
        }

        Schema::table('api_connections', function (Blueprint $table) {
            $table->string('twilio_account_sid')->nullable()->after('instagram_account_id');
            $table->string('twilio_phone_number')->nullable()->after('twilio_account_sid');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('api_connections', function (Blueprint $table) {
            $table->dropColumn(['twilio_account_sid', 'twilio_phone_number']);
        });

        $driver = Schema::getConnection()->getDriverName();

        if ($driver !== 'sqlite') {
            DB::statement("ALTER TABLE api_connections MODIFY channel ENUM('whatsapp', 'instagram') NOT NULL");
        }
    }
};
