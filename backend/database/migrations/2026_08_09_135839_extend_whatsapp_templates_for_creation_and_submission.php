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
        Schema::table('whatsapp_templates', function (Blueprint $table) {
            $table->string('meta_template_id')->nullable()->change();
            $table->timestamp('synced_at')->nullable()->change();
            $table->json('components')->nullable()->after('variables');
            $table->foreignId('created_by_user_id')->nullable()->after('components')->constrained('users')->nullOnDelete();
            $table->text('rejection_reason')->nullable()->after('created_by_user_id');
            $table->timestamp('submitted_at')->nullable()->after('rejection_reason');
        });

        $driver = Schema::getConnection()->getDriverName();

        if ($driver === 'sqlite') {
            $schemaVersion = (int) DB::selectOne('PRAGMA schema_version')->schema_version;

            DB::statement('PRAGMA writable_schema = 1');
            DB::statement(
                "UPDATE sqlite_master SET sql = REPLACE(sql, \"'approved','pending','rejected'\", \"'draft','pending','approved','rejected'\") WHERE name = 'whatsapp_templates' AND type = 'table'"
            );
            DB::statement('PRAGMA schema_version = '.($schemaVersion + 1));
            DB::statement('PRAGMA writable_schema = 0');
        } else {
            DB::statement("ALTER TABLE whatsapp_templates MODIFY status ENUM('draft', 'pending', 'approved', 'rejected') NOT NULL DEFAULT 'draft'");
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        $driver = Schema::getConnection()->getDriverName();

        if ($driver !== 'sqlite') {
            DB::statement("ALTER TABLE whatsapp_templates MODIFY status ENUM('approved', 'pending', 'rejected') NOT NULL DEFAULT 'pending'");
        }

        Schema::table('whatsapp_templates', function (Blueprint $table) {
            $table->dropForeign(['created_by_user_id']);
            $table->dropColumn(['components', 'created_by_user_id', 'rejection_reason', 'submitted_at']);
            $table->string('meta_template_id')->nullable(false)->change();
            $table->timestamp('synced_at')->nullable(false)->change();
        });
    }
};
