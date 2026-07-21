<?php

namespace Board\PluginShelf\Models;

use App\Models\Board;
use Board\PluginShelf\ShelfPlugin;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Per-board Shelf state. Currently the admin's quota override; a null
 * quota_gb falls back to the instance-wide default from the plugin settings.
 */
#[Fillable(['board_id', 'quota_gb'])]
class ShelfBoard extends Model
{
    public function board(): BelongsTo
    {
        return $this->belongsTo(Board::class);
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'quota_gb' => 'integer',
        ];
    }

    /**
     * The effective quota for a board, in GB (override or instance default).
     */
    public static function quotaGbFor(Board $board): int
    {
        $override = static::where('board_id', $board->id)->value('quota_gb');

        return $override !== null && (int) $override > 0
            ? (int) $override
            : ShelfPlugin::defaultQuotaGb();
    }

    public static function quotaBytesFor(Board $board): int
    {
        return static::quotaGbFor($board) * 1024 ** 3;
    }
}
