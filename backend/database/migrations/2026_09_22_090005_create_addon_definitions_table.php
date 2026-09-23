<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('addon_definitions', function (Blueprint $table) {
            $table->id();
            $table->uuid()->unique();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->unsignedInteger('price')->default(0);
            $table->unsignedInteger('max_quantity')->default(1);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('addon_definition_product', function (Blueprint $table) {
            $table->id();
            $table->foreignId('addon_definition_id')->constrained()->cascadeOnDelete();
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['addon_definition_id', 'product_id'], 'addon_def_product_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('addon_definition_product');
        Schema::dropIfExists('addon_definitions');
    }
};
