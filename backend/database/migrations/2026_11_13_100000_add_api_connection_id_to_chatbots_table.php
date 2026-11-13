<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('chatbots', function (Blueprint $table) {
            $table->foreignId('api_connection_id')
                ->nullable()
                ->after('company_id')
                ->constrained('api_connections')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('chatbots', function (Blueprint $table) {
            $table->dropConstrainedForeignId('api_connection_id');
        });
    }
};
