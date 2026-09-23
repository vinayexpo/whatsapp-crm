<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('commerce_settings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->unique()->constrained()->cascadeOnDelete();
            $table->foreignId('business_type_id')->nullable()->constrained()->nullOnDelete();
            $table->string('currency', 3)->default('INR');
            $table->unsignedInteger('default_tax_rate_bp')->default(0);
            $table->string('order_number_prefix')->nullable();
            $table->unsignedInteger('session_timeout_minutes')->default(30);
            $table->json('settings')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('commerce_settings');
    }
};
