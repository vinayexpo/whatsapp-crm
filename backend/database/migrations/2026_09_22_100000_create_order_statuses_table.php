<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('order_statuses', function (Blueprint $table) {
            $table->id();
            $table->uuid()->unique();
            // Nullable = system-default row for the business_type, cloned into
            // a company-owned row at onboarding so each company can freely
            // edit labels/transitions/notification templates independently.
            $table->foreignId('company_id')->nullable()->constrained()->cascadeOnDelete();
            $table->foreignId('business_type_id')->nullable()->constrained()->nullOnDelete();
            $table->string('slug');
            $table->string('label');
            $table->unsignedInteger('sort_order')->default(0);
            $table->boolean('is_terminal')->default(false);
            $table->boolean('is_cancellable_from')->default(true);
            $table->boolean('notify_customer')->default(false);
            $table->foreignId('notification_template_id')->nullable()->constrained('whatsapp_templates')->nullOnDelete();
            $table->json('allowed_next_status_ids')->nullable();
            $table->timestamps();

            $table->index(['company_id', 'business_type_id', 'slug']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('order_statuses');
    }
};
