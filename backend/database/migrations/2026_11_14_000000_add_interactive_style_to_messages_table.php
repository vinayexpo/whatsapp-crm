<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('messages', function (Blueprint $table) {
            $table->enum('interactive_style', ['button', 'list'])->nullable()->after('buttons');
            $table->string('list_button_label')->nullable()->after('interactive_style');
        });
    }

    public function down(): void
    {
        Schema::table('messages', function (Blueprint $table) {
            $table->dropColumn(['interactive_style', 'list_button_label']);
        });
    }
};
