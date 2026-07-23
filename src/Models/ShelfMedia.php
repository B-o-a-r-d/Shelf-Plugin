<?php

namespace Board\PluginShelf\Models;

use App\Models\Board;
use App\Models\User;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * One image embedded inline in a note's markdown. Lives on the private disk,
 * served by ShelfMediaController; its bytes count against the board quota. The
 * `note_id` records which note it was uploaded into so that note's public share
 * can expose the image without authentication.
 */
#[Fillable(['board_id', 'note_id', 'public_id', 'path', 'mime', 'size', 'created_by'])]
class ShelfMedia extends Model
{
    protected $table = 'shelf_media';

    protected static function booted(): void
    {
        static::creating(function (self $media): void {
            if (empty($media->public_id)) {
                $media->public_id = (string) Str::ulid();
            }
        });

        static::deleted(function (self $media): void {
            rescue(fn () => Storage::disk('local')->deleteDirectory(dirname($media->path)), report: false);
        });
    }

    public function getRouteKeyName(): string
    {
        return 'public_id';
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'size' => 'integer',
        ];
    }

    public function board(): BelongsTo
    {
        return $this->belongsTo(Board::class);
    }

    public function note(): BelongsTo
    {
        return $this->belongsTo(ShelfNode::class, 'note_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * Bytes of inline media stored on a board (added to the node bytes for the
     * quota gauge).
     */
    public static function usedBytes(Board $board): int
    {
        return (int) static::where('board_id', $board->id)->sum('size');
    }
}
