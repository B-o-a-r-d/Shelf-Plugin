<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Make note full-text search accent-insensitive ("etagere" matches "étagère").
 * Rebuilds the generated tsvector over an unaccented copy of the markdown.
 *
 * unaccent() is not IMMUTABLE by default, so it can't sit in a GENERATED column
 * expression — it is wrapped in an immutable helper (dictionary pinned). If the
 * extension can't be installed (permissions), the column falls back to the plain
 * accent-sensitive vector so search keeps working. Postgres-only.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (DB::connection()->getDriverName() !== 'pgsql') {
            return;
        }

        $hasUnaccent = rescue(function (): bool {
            DB::statement('CREATE EXTENSION IF NOT EXISTS unaccent');

            return true;
        }, false, report: false);

        DB::statement('DROP INDEX IF EXISTS shelf_notes_search_idx');
        DB::statement('ALTER TABLE shelf_notes DROP COLUMN IF EXISTS search_vector');

        if ($hasUnaccent) {
            DB::statement(<<<'SQL'
                CREATE OR REPLACE FUNCTION shelf_immutable_unaccent(text)
                    RETURNS text
                    LANGUAGE sql IMMUTABLE PARALLEL SAFE STRICT AS
                $$ SELECT unaccent('unaccent'::regdictionary, $1) $$
            SQL);

            DB::statement(<<<'SQL'
                ALTER TABLE shelf_notes
                    ADD COLUMN search_vector tsvector
                    GENERATED ALWAYS AS (to_tsvector('simple', shelf_immutable_unaccent(coalesce(markdown, '')))) STORED
            SQL);
        } else {
            DB::statement(<<<'SQL'
                ALTER TABLE shelf_notes
                    ADD COLUMN search_vector tsvector
                    GENERATED ALWAYS AS (to_tsvector('simple', coalesce(markdown, ''))) STORED
            SQL);
        }

        DB::statement('CREATE INDEX shelf_notes_search_idx ON shelf_notes USING gin (search_vector)');
    }

    public function down(): void
    {
        if (DB::connection()->getDriverName() !== 'pgsql') {
            return;
        }

        DB::statement('DROP INDEX IF EXISTS shelf_notes_search_idx');
        DB::statement('ALTER TABLE shelf_notes DROP COLUMN IF EXISTS search_vector');
        DB::statement("ALTER TABLE shelf_notes ADD COLUMN search_vector tsvector GENERATED ALWAYS AS (to_tsvector('simple', coalesce(markdown, ''))) STORED");
        DB::statement('CREATE INDEX shelf_notes_search_idx ON shelf_notes USING gin (search_vector)');
        DB::statement('DROP FUNCTION IF EXISTS shelf_immutable_unaccent(text)');
    }
};
