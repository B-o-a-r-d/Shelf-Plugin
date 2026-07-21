<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('shelf_notes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('node_id')->unique()->constrained('shelf_nodes')->cascadeOnDelete();
            $table->longText('markdown')->nullable();
            $table->unsignedInteger('version')->default(1);
            $table->timestamps();
        });

        Schema::create('shelf_note_revisions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('note_id')->constrained('shelf_notes')->cascadeOnDelete();
            $table->longText('markdown')->nullable();
            $table->unsignedBigInteger('size')->default(0);
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('created_at');

            $table->index(['note_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('shelf_note_revisions');
        Schema::dropIfExists('shelf_notes');
    }
};
