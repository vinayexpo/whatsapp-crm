<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('campaigns', function (Blueprint $table) {
            $table->foreignId('phonebook_folder_id')->nullable()->after('audience_tag')->constrained()->nullOnDelete();
            $table->string('audience_tag')->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('campaigns', function (Blueprint $table) {
            $table->dropConstrainedForeignId('phonebook_folder_id');
            $table->string('audience_tag')->nullable(false)->change();
        });
    }
};
