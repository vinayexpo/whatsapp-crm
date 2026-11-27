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
        Schema::table('commerce_settings', function (Blueprint $table) {
            $table->foreignId('api_connection_id')->nullable()->constrained()->nullOnDelete();
            $table->boolean('sync_enabled')->default(false);
            $table->timestamp('last_sync_at')->nullable();
            $table->text('sync_error')->nullable();
            $table->string('payment_configuration_name', 60)->nullable();
            $table->boolean('whatsapp_pay_enabled')->default(false);
            $table->text('razorpay_key_id')->nullable();
            $table->text('razorpay_key_secret')->nullable();
            $table->text('razorpay_webhook_secret')->nullable();
        });
        Schema::table('products', function (Blueprint $table) {
            $table->json('meta_snapshot')->nullable();
            $table->string('meta_sync_state')->default('local');
            $table->text('meta_sync_error')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('products', fn (Blueprint $table) => $table->dropColumn(['meta_snapshot', 'meta_sync_state', 'meta_sync_error']));
        Schema::table('commerce_settings', function (Blueprint $table) {
            $table->dropConstrainedForeignId('api_connection_id');
            $table->dropColumn(['sync_enabled', 'last_sync_at', 'sync_error', 'payment_configuration_name', 'whatsapp_pay_enabled', 'razorpay_key_id', 'razorpay_key_secret', 'razorpay_webhook_secret']);
        });
    }
};
