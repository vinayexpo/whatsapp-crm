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
        Schema::table('campaigns', function (Blueprint $table) {
            $table->foreignId('whatsapp_template_id')->nullable()->after('message')->constrained()->nullOnDelete();
            $table->json('template_variables')->nullable()->after('whatsapp_template_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('campaigns', function (Blueprint $table) {
            $table->dropConstrainedForeignId('whatsapp_template_id');
            $table->dropColumn('template_variables');
        });
    }
};
