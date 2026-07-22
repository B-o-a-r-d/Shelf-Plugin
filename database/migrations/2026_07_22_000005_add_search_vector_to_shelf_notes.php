<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Full-text search is a Postgres feature; other drivers (sqlite in
        // tests) fall back to a portable LIKE in the search code. A STORED
        // generated column is auto-computed for existing rows on ALTER and
        // recomputed whenever `markdown` changes — no backfill, no triggers.
        // 'simple' config: language-agnostic tokenisation (no stemming), fit
        // for a fr/en/es document shelf.
        if (DB::connection()->getDriverName() !== 'pgsql') {
            return;
        }

        DB::statement("ALTER TABLE shelf_notes ADD COLUMN search_vector tsvector GENERATED ALWAYS AS (to_tsvector('simple', coalesce(markdown, ''))) STORED");
        DB::statement('CREATE INDEX shelf_notes_search_idx ON shelf_notes USING gin (search_vector)');
    }

    public function down(): void
    {
        if (DB::connection()->getDriverName() !== 'pgsql') {
            return;
        }

        DB::statement('DROP INDEX IF EXISTS shelf_notes_search_idx');

        Schema::table('shelf_notes', function ($table) {
            $table->dropColumn('search_vector');
        });
    }
};
