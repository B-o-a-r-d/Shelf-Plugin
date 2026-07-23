<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Google-Docs-style comments on notes. A root comment anchors to a quoted text
 * span (re-anchored by searching the live content, since notes persist as
 * markdown — no stored marks); replies chain to the root via parent_id. Resolve
 * state lives on the root.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('shelf_note_comments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('board_id')->constrained()->cascadeOnDelete();
            $table->foreignId('note_id')->constrained('shelf_nodes')->cascadeOnDelete();
            $table->string('public_id', 26)->unique();
            $table->foreignId('parent_id')->nullable()->constrained('shelf_note_comments')->cascadeOnDelete();

            // Anchor (root comments only): the quoted selection + a short prefix
            // to disambiguate repeats + a char offset hint for ordering/re-anchor.
            $table->text('anchor_quote')->nullable();
            $table->string('anchor_prefix', 64)->nullable();
            $table->unsignedInteger('anchor_start')->nullable();

            $table->text('body');
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('resolved_at')->nullable();
            $table->foreignId('resolved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['note_id', 'parent_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('shelf_note_comments');
    }
};
