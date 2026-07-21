<?php

namespace Board\PluginShelf\Models;

use App\Models\Board;
use App\Models\User;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

/**
 * One entry of a Shelf board's explorer tree: a folder, a markdown note or a
 * stored file. The tree is a plain adjacency list (parent_id) ordered by
 * position within each folder.
 *
 * Trashing is soft (archived_at) and applies to the node only: its subtree
 * stays attached and simply disappears from the tree render, so restoring the
 * node restores the whole branch. Permanent deletion cascades to descendants
 * via the parent_id foreign key.
 */
#[Fillable(['board_id', 'parent_id', 'type', 'name', 'position', 'size', 'created_by', 'archived_at'])]
class ShelfNode extends Model
{
    public const TYPE_FOLDER = 'folder';

    public const TYPE_NOTE = 'note';

    public const TYPE_FILE = 'file';

    /** Days a trashed node survives before the scheduled purge deletes it. */
    public const TRASH_RETENTION_DAYS = 30;

    protected static function booted(): void
    {
        static::creating(function (self $node): void {
            if (empty($node->public_id)) {
                $node->public_id = (string) Str::ulid();
            }
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
            'archived_at' => 'datetime',
            'position' => 'integer',
            'size' => 'integer',
        ];
    }

    public function board(): BelongsTo
    {
        return $this->belongsTo(Board::class);
    }

    public function parent(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_id');
    }

    public function children(): HasMany
    {
        return $this->hasMany(self::class, 'parent_id')->orderBy('position');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function isFolder(): bool
    {
        return $this->type === self::TYPE_FOLDER;
    }

    public function isTrashed(): bool
    {
        return $this->archived_at !== null;
    }

    /**
     * @param  Builder<ShelfNode>  $query
     */
    public function scopeNotArchived(Builder $query): void
    {
        $query->whereNull('archived_at');
    }

    /**
     * Bytes currently stored on a board (files + note contents + revisions all
     * report through the nodes' size column).
     */
    public static function usedBytes(Board $board): int
    {
        return (int) static::where('board_id', $board->id)->sum('size');
    }

    /**
     * Delete nodes whose trash retention has elapsed. Descendants follow via
     * the parent_id cascade. Called daily by the plugin's scheduled task.
     */
    public static function purgeExpiredTrash(): int
    {
        $expired = static::whereNotNull('archived_at')
            ->where('archived_at', '<', now()->subDays(self::TRASH_RETENTION_DAYS))
            ->get();

        foreach ($expired as $node) {
            $node->delete();
        }

        return $expired->count();
    }
}
