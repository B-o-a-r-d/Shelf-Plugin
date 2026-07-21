<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('shelf_boards', function (Blueprint $table) {
            $table->id();
            $table->foreignId('board_id')->unique()->constrained()->cascadeOnDelete();
            $table->unsignedInteger('quota_gb')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('shelf_boards');
    }
};
