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
            $table->boolean('calling_enabled')->default(false)->after('twilio_phone_number');
            $table->enum('calling_status', ['disabled', 'pending', 'active'])->default('disabled')->after('calling_enabled');
            $table->timestamp('calling_verified_at')->nullable()->after('calling_status');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('api_connections', function (Blueprint $table) {
            $table->dropColumn(['calling_enabled', 'calling_status', 'calling_verified_at']);
        });
    }
};
