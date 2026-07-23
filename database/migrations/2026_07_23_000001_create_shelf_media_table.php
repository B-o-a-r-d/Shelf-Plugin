<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Images embedded inline in note markdown (![](url)). Stored on the private disk
 * and served by a dedicated route; counted against the board quota like any
 * other stored bytes. Tied to the note they were uploaded into so a publicly
 * shared note can expose its images without auth.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('shelf_media', function (Blueprint $table) {
            $table->id();
            $table->foreignId('board_id')->constrained()->cascadeOnDelete();
            $table->foreignId('note_id')->nullable()->constrained('shelf_nodes')->nullOnDelete();
            $table->string('public_id', 26)->unique();
            $table->string('path')->nullable();
            $table->string('mime');
            $table->unsignedBigInteger('size')->default(0);
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index('board_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('shelf_media');
    }
};
