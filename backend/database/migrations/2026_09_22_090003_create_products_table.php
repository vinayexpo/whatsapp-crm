<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('products', function (Blueprint $table) {
            $table->id();
            $table->uuid()->unique();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('category_id')->nullable()->constrained()->nullOnDelete();
            $table->string('brand')->nullable();
            $table->string('name');
            $table->string('sku')->nullable();
            $table->text('description')->nullable();
            $table->json('images')->nullable();
            $table->unsignedInteger('base_price');
            $table->unsignedInteger('sale_price')->nullable();
            $table->unsignedInteger('tax_rate_bp')->default(0);
            $table->unsignedInteger('weight_grams')->nullable();
            $table->json('dimensions')->nullable();
            $table->json('attributes')->nullable();
            $table->boolean('delivery_available')->default(true);
            $table->boolean('pickup_available')->default(true);
            $table->boolean('is_service')->default(false);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['company_id', 'sku']);
            $table->index(['company_id', 'category_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('products');
    }
};
