<?php

namespace Board\PluginShelf\Support;

use Board\PluginShelf\Models\ShelfNote;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Shared note search: accent-insensitive name matching plus Postgres full-text
 * over note content (prefix-matched, ranked, unaccented where the extension is
 * present) with a portable LIKE fallback. Used by the Shelf page and the MCP
 * search tool so both behave identically.
 */
class ShelfSearch
{
    /**
     * Accent-insensitive substring match for node names ("etagere" ~ "étagère").
     */
    public static function nameMatches(string $name, string $query): bool
    {
        return stripos(Str::ascii($name), Str::ascii($query)) !== false;
    }

    /**
     * Notes whose content matches the query, ranked. Postgres full-text with
     * prefix matching (folded through the unaccent helper when available); a
     * case-insensitive LIKE otherwise (sqlite in tests).
     *
     * @param  Collection<int, int>  $noteIds
     * @return Collection<int, ShelfNote>
     */
    public static function noteContents(Collection $noteIds, string $query): Collection
    {
        if ($noteIds->isEmpty()) {
            return collect();
        }

        $base = ShelfNote::whereIn('node_id', $noteIds);

        if (DB::connection()->getDriverName() === 'pgsql') {
            $tsquery = self::toPrefixTsQuery($query);

            if ($tsquery === '') {
                return collect();
            }

            $tsqExpr = self::usesUnaccent()
                ? "to_tsquery('simple', shelf_immutable_unaccent(?))"
                : "to_tsquery('simple', ?)";

            return $base
                ->whereRaw("search_vector @@ {$tsqExpr}", [$tsquery])
                ->orderByRaw("ts_rank(search_vector, {$tsqExpr}) desc", [$tsquery])
                ->limit(30)
                ->get(['node_id', 'markdown']);
        }

        return $base
            ->whereRaw('LOWER(markdown) LIKE ?', ['%'.mb_strtolower($query).'%'])
            ->get(['node_id', 'markdown']);
    }

    /**
     * Turn free text into a safe prefix tsquery — "pomm gold" becomes
     * "pomm:* & gold:*". Only word/number tokens reach to_tsquery, so no user
     * input can break its syntax.
     */
    public static function toPrefixTsQuery(string $query): string
    {
        preg_match_all('/[\p{L}\p{N}]+/u', mb_strtolower($query), $matches);

        $terms = array_slice($matches[0], 0, 6);

        return implode(' & ', array_map(fn (string $term): string => $term.':*', $terms));
    }

    /**
     * Whether the search vector was built through the unaccent helper (present
     * when the `unaccent` extension could be installed). Cached per request.
     */
    public static function usesUnaccent(): bool
    {
        static $uses = null;

        if ($uses === null) {
            $uses = DB::table('pg_proc')->where('proname', 'shelf_immutable_unaccent')->exists();
        }

        return $uses;
    }

    /**
     * A ±60-char plain-text snippet around the first matching term in the note.
     */
    public static function snippet(string $markdown, string $query): string
    {
        $position = mb_stripos($markdown, $query);

        if ($position === false) {
            // The tsquery matched a prefix — locate the first query word.
            preg_match('/[\p{L}\p{N}]+/u', $query, $word);
            $position = $word !== [] ? mb_stripos($markdown, $word[0]) : false;
        }

        $position = $position === false ? 0 : $position;
        $start = max(0, $position - 60);

        return ($start > 0 ? '…' : '')
            .trim(mb_substr($markdown, $start, 120 + mb_strlen($query)))
            .'…';
    }
}
