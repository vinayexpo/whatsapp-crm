<?php

use App\Models\ApiConnection;
use App\Models\Conversation;
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
            $table->foreignId('api_connection_id')->nullable()->after('chatbot_id')->constrained('api_connections')->nullOnDelete();
        });

        Conversation::query()
            ->whereNull('api_connection_id')
            ->select('id', 'channel', 'company_id')
            ->chunkById(500, function ($conversations) {
                foreach ($conversations as $conversation) {
                    $connectionId = ApiConnection::query()
                        ->where('company_id', $conversation->company_id)
                        ->where('channel', $conversation->channel)
                        ->limit(2)
                        ->pluck('id');

                    if ($connectionId->count() === 1) {
                        Conversation::where('id', $conversation->id)->update(['api_connection_id' => $connectionId->first()]);
                    }
                }
            });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('conversations', function (Blueprint $table) {
            $table->dropConstrainedForeignId('api_connection_id');
        });
    }
};
