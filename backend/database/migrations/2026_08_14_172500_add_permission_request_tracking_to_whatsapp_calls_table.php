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
        Schema::table('whatsapp_calls', function (Blueprint $table) {
            $table->string('permission_request_message_id')->nullable();
            $table->enum('permission_request_status', [
                'sent', 'delivered', 'read', 'failed',
            ])->nullable();
            $table->string('permission_request_failure_reason')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('whatsapp_calls', function (Blueprint $table) {
            $table->dropColumn([
                'permission_request_message_id',
                'permission_request_status',
                'permission_request_failure_reason',
            ]);
        });
    }
};
