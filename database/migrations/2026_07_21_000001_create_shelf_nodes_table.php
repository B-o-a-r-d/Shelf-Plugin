<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('shelf_nodes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('board_id')->constrained()->cascadeOnDelete();
            $table->foreignId('parent_id')->nullable()->constrained('shelf_nodes')->cascadeOnDelete();
            $table->string('type'); // folder | note | file
            $table->string('name');
            $table->unsignedInteger('position')->default(0);
            $table->unsignedBigInteger('size')->default(0);
            $table->string('public_id', 26)->unique();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('archived_at')->nullable();
            $table->timestamps();

            $table->index(['board_id', 'parent_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('shelf_nodes');
    }
};
