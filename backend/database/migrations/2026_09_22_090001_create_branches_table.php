<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('branches', function (Blueprint $table) {
            $table->id();
            $table->uuid()->unique();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('api_connection_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('business_type_id')->nullable()->constrained()->nullOnDelete();
            $table->string('name');
            $table->string('slug');
            $table->string('address')->nullable();
            $table->decimal('latitude', 10, 7)->nullable();
            $table->decimal('longitude', 10, 7)->nullable();
            $table->string('phone')->nullable();
            $table->enum('status', ['active', 'inactive'])->default('active');
            $table->json('operating_hours')->nullable();
            $table->json('holidays')->nullable();
            $table->string('timezone')->default('Asia/Kolkata');
            $table->unsignedInteger('min_order_amount')->nullable();
            $table->unsignedInteger('default_delivery_charge')->nullable();
            $table->decimal('delivery_radius_km', 6, 2)->nullable();
            $table->string('currency', 3)->default('INR');
            $table->boolean('is_default')->default(false);
            $table->timestamps();

            $table->index(['company_id', 'api_connection_id']);
            $table->unique(['company_id', 'slug']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('branches');
    }
};
