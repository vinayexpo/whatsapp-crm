<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('instagram_comments', function (Blueprint $table) {
            $table->id();
            $table->uuid()->unique();
            $table->foreignId('company_id')->nullable()->constrained()->cascadeOnDelete();
            $table->string('comment_id')->unique();
            $table->foreignId('contact_id')->constrained()->cascadeOnDelete();
            $table->string('media_id')->nullable();
            $table->text('text')->nullable();
            $table->timestamp('replied_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('instagram_comments');
    }
};
