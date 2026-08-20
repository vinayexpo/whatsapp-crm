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
        Schema::table('conversations', function (Blueprint $table) {
            $table->foreignId('current_chat_flow_id')->nullable()->after('chatbot_id')->constrained('chat_menu_flows')->nullOnDelete();
            $table->string('current_chat_flow_node_id')->nullable()->after('current_chat_flow_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('conversations', function (Blueprint $table) {
            $table->dropConstrainedForeignId('current_chat_flow_id');
            $table->dropColumn('current_chat_flow_node_id');
        });
    }
};
