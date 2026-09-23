<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('commerce_settings', function (Blueprint $table) {
            $table->string('meta_catalog_id')->nullable()->after('business_type_id');
        });
    }

    public function down(): void
    {
        Schema::table('commerce_settings', function (Blueprint $table) {
            $table->dropColumn('meta_catalog_id');
        });
    }
};
