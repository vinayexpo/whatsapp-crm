<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->string('availability')->default('in_stock')->after('is_active');
            $table->string('condition')->default('new')->after('availability');
            $table->string('product_url')->nullable()->after('condition');
            $table->string('gtin')->nullable()->after('product_url');
            $table->string('mpn')->nullable()->after('gtin');
        });
    }

    public function down(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->dropColumn(['availability', 'condition', 'product_url', 'gtin', 'mpn']);
        });
    }
};
