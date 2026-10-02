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
        Schema::table('whatsapp_call_flows', function (Blueprint $table) {
            $table->enum('language', ['en', 'hi', 'te'])->default('en');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('whatsapp_call_flows', function (Blueprint $table) {
            $table->dropColumn('language');
        });
    }
};
