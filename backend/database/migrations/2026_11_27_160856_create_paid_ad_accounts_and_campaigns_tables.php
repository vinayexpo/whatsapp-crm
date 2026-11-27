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
        Schema::create('paid_ad_accounts', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('meta_account_id');
            $table->text('access_token');
            $table->string('page_id');
            $table->string('instagram_id')->nullable();
            $table->string('whatsapp_number')->nullable();
            $table->string('currency', 3)->nullable();
            $table->string('timezone')->nullable();
            $table->timestamp('verified_at')->nullable();
            $table->timestamp('synced_at')->nullable();
            $table->text('sync_error')->nullable();
            $table->timestamps();
            $table->unique(['company_id', 'meta_account_id']);
        });
        Schema::create('paid_ad_campaigns', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('paid_ad_account_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('objective');
            $table->string('state')->default('DRAFT');
            $table->string('remote_id')->nullable();
            $table->string('adset_id')->nullable();
            $table->string('creative_id')->nullable();
            $table->string('ad_id')->nullable();
            $table->string('remote_status')->nullable();
            $table->json('specification')->nullable();
            $table->json('insights')->nullable();
            $table->text('last_error')->nullable();
            $table->timestamp('synced_at')->nullable();
            $table->timestamps();
            $table->unique(['paid_ad_account_id', 'remote_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('paid_ad_campaigns');
        Schema::dropIfExists('paid_ad_accounts');
    }
};
