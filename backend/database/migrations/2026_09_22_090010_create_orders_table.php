<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('orders', function (Blueprint $table) {
            $table->id();
            $table->uuid()->unique();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('branch_id')->constrained()->cascadeOnDelete();
            $table->foreignId('contact_id')->constrained()->cascadeOnDelete();
            $table->foreignId('conversation_id')->nullable()->constrained()->nullOnDelete();
            $table->string('order_number');
            // No order_statuses table until Phase D — a simple string slug is
            // the status of record for now; OrderStatusTransitionService and
            // the FK to order_statuses.id arrive with Phase D's data-driven
            // status machine.
            $table->string('status')->default('pending');
            $table->enum('fulfillment_type', ['delivery', 'pickup'])->default('delivery');
            $table->string('delivery_address')->nullable();
            $table->decimal('delivery_lat', 10, 7)->nullable();
            $table->decimal('delivery_lng', 10, 7)->nullable();
            $table->unsignedInteger('subtotal');
            $table->unsignedInteger('tax_total')->default(0);
            $table->unsignedInteger('delivery_charge')->default(0);
            $table->unsignedInteger('discount_total')->default(0);
            $table->unsignedInteger('grand_total');
            $table->string('currency', 3)->default('INR');
            // gateway:razorpay arrives in Phase C; cod/upi are the only
            // values ever written by the Phase B engine.
            $table->string('payment_method')->default('cod');
            $table->string('payment_status')->default('pending');
            $table->foreignId('assigned_staff_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->text('notes')->nullable();
            $table->timestamp('placed_at')->nullable();
            $table->timestamps();

            $table->unique(['company_id', 'order_number']);
            $table->index(['company_id', 'branch_id', 'status']);
            $table->index(['company_id', 'contact_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('orders');
    }
};
