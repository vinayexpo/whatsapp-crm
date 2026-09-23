<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->string('meta_retailer_id')->nullable()->after('sku');
            $table->timestamp('meta_synced_at')->nullable()->after('meta_retailer_id');
        });
    }

    public function down(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->dropColumn(['meta_retailer_id', 'meta_synced_at']);
        });
    }
};
