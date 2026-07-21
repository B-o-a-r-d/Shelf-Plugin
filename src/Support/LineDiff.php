<?php

namespace Board\PluginShelf\Support;

/**
 * Line-level diff between two markdown documents, for the revision panel.
 * Classic LCS; above the size guard it degrades to a plain delete-all /
 * add-all view rather than burning CPU on huge notes.
 */
final class LineDiff
{
    private const MAX_CELLS = 250_000;

    /**
     * @return array<int, array{type: 'same'|'add'|'del', text: string}>
     */
    public static function compute(string $old, string $new): array
    {
        $a = $old === '' ? [] : preg_split("/\r\n|\r|\n/", $old);
        $b = $new === '' ? [] : preg_split("/\r\n|\r|\n/", $new);

        $m = count($a);
        $n = count($b);

        if ($m * $n > self::MAX_CELLS) {
            return array_merge(
                array_map(fn (string $line): array => ['type' => 'del', 'text' => $line], $a),
                array_map(fn (string $line): array => ['type' => 'add', 'text' => $line], $b),
            );
        }

        // LCS length table, then backtrack into an edit script.
        $lcs = array_fill(0, $m + 1, array_fill(0, $n + 1, 0));

        for ($i = $m - 1; $i >= 0; $i--) {
            for ($j = $n - 1; $j >= 0; $j--) {
                $lcs[$i][$j] = $a[$i] === $b[$j]
                    ? $lcs[$i + 1][$j + 1] + 1
                    : max($lcs[$i + 1][$j], $lcs[$i][$j + 1]);
            }
        }

        $out = [];
        $i = $j = 0;

        while ($i < $m && $j < $n) {
            if ($a[$i] === $b[$j]) {
                $out[] = ['type' => 'same', 'text' => $a[$i]];
                $i++;
                $j++;
            } elseif ($lcs[$i + 1][$j] >= $lcs[$i][$j + 1]) {
                $out[] = ['type' => 'del', 'text' => $a[$i]];
                $i++;
            } else {
                $out[] = ['type' => 'add', 'text' => $b[$j]];
                $j++;
            }
        }

        for (; $i < $m; $i++) {
            $out[] = ['type' => 'del', 'text' => $a[$i]];
        }

        for (; $j < $n; $j++) {
            $out[] = ['type' => 'add', 'text' => $b[$j]];
        }

        return $out;
    }
}
