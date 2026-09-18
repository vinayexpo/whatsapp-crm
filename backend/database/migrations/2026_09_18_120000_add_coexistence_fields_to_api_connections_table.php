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
        Schema::table('api_connections', function (Blueprint $table) {
            $table->string('onboarding_type')->default('manual')->after('channel');
            $table->timestamp('smb_app_linked_at')->nullable()->after('connected_at');
            $table->string('wa_business_app_phone_number')->nullable()->after('smb_app_linked_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('api_connections', function (Blueprint $table) {
            $table->dropColumn(['onboarding_type', 'smb_app_linked_at', 'wa_business_app_phone_number']);
        });
    }
};
