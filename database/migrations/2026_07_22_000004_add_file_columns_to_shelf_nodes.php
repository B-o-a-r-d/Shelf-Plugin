<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('shelf_nodes', function (Blueprint $table) {
            $table->string('mime')->nullable()->after('size');
            $table->string('file_path', 1024)->nullable()->after('mime');
        });
    }

    public function down(): void
    {
        Schema::table('shelf_nodes', function (Blueprint $table) {
            $table->dropColumn(['mime', 'file_path']);
        });
    }
};
