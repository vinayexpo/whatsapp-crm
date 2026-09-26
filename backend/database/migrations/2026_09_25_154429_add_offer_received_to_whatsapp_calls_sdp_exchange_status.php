<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private array $withOfferReceived = [
        'pending_offer', 'offer_sent', 'offer_received', 'answer_received', 'connected', 'failed',
    ];

    private array $withoutOfferReceived = [
        'pending_offer', 'offer_sent', 'answer_received', 'connected', 'failed',
    ];

    /**
     * Run the migrations.
     */
    public function up(): void
    {
        if (Schema::getConnection()->getDriverName() === 'mysql') {
            $values = collect($this->withOfferReceived)->map(fn ($v) => "'{$v}'")->implode(', ');
            DB::statement("ALTER TABLE whatsapp_calls MODIFY sdp_exchange_status ENUM({$values}) NOT NULL DEFAULT 'pending_offer'");

            return;
        }

        Schema::table('whatsapp_calls', function (Blueprint $table) {
            $table->enum('sdp_exchange_status', $this->withOfferReceived)->default('pending_offer')->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (Schema::getConnection()->getDriverName() === 'mysql') {
            $values = collect($this->withoutOfferReceived)->map(fn ($v) => "'{$v}'")->implode(', ');
            DB::statement("ALTER TABLE whatsapp_calls MODIFY sdp_exchange_status ENUM({$values}) NOT NULL DEFAULT 'pending_offer'");

            return;
        }

        Schema::table('whatsapp_calls', function (Blueprint $table) {
            $table->enum('sdp_exchange_status', $this->withoutOfferReceived)->default('pending_offer')->change();
        });
    }
};
