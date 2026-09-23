<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('order_sessions', function (Blueprint $table) {
            $table->id();
            $table->uuid()->unique();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('conversation_id')->constrained()->cascadeOnDelete();
            $table->foreignId('contact_id')->constrained()->cascadeOnDelete();
            $table->foreignId('branch_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('api_connection_id')->nullable()->constrained()->nullOnDelete();
            $table->enum('status', ['active', 'completed', 'abandoned', 'expired'])->default('active');
            $table->enum('step', [
                'welcome',
                'branch_selection',
                'category_browse',
                'product_browse',
                'product_detail',
                'variant_addon_selection',
                'cart_review',
                'customer_details',
                'delivery_or_pickup',
                'payment_method',
                'order_confirmation',
                'completed',
            ])->default('welcome');
            $table->json('context')->nullable();
            $table->foreignId('order_id')->nullable()->constrained()->nullOnDelete();
            $table->timestamp('last_interaction_at')->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->timestamps();

            $table->index(['company_id', 'conversation_id']);
            $table->index(['company_id', 'status', 'expires_at']);
        });

        // Only one active session per conversation, enforced via a generated
        // column that collapses to NULL for any non-active row (MySQL has no
        // native partial/filtered unique index, so this is the standard
        // workaround: a unique index over a column that is NULL except when
        // the constraint should apply).
        if (Schema::getConnection()->getDriverName() === 'sqlite') {
            DB::statement(
                'ALTER TABLE order_sessions ADD COLUMN active_conversation_id INTEGER '.
                "GENERATED ALWAYS AS (CASE WHEN status = 'active' THEN conversation_id ELSE NULL END) VIRTUAL"
            );
            DB::statement('CREATE UNIQUE INDEX order_sessions_active_conversation_unique ON order_sessions (active_conversation_id)');
        } else {
            DB::statement(
                'ALTER TABLE order_sessions ADD COLUMN active_conversation_id BIGINT UNSIGNED '.
                "GENERATED ALWAYS AS (CASE WHEN status = 'active' THEN conversation_id ELSE NULL END) VIRTUAL"
            );
            DB::statement('ALTER TABLE order_sessions ADD UNIQUE INDEX order_sessions_active_conversation_unique (active_conversation_id)');
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('order_sessions');
    }
};
