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
            $table->string('waba_id')->nullable()->after('identifier');
            $table->string('phone_number_id')->nullable()->after('waba_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('api_connections', function (Blueprint $table) {
            $table->dropColumn(['waba_id', 'phone_number_id']);
        });
    }
};
