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
        Schema::table('webhook_events', function (Blueprint $table) {
            $table->string('status')->default('pending')->after('payload');
            $table->text('error')->nullable()->after('status');
            $table->unsignedTinyInteger('attempts')->default(0)->after('error');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('webhook_events', function (Blueprint $table) {
            $table->dropColumn(['status', 'error', 'attempts']);
        });
    }
};
