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
            $table->string('instagram_account_id')->nullable()->after('phone_number_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('api_connections', function (Blueprint $table) {
            $table->dropColumn('instagram_account_id');
        });
    }
};
