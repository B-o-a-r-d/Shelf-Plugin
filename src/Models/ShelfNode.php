<?php

namespace Board\PluginShelf\Models;

use App\Models\Board;
use App\Models\User;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Storage;
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
#[Fillable(['board_id', 'parent_id', 'type', 'name', 'position', 'size', 'mime', 'file_path', 'created_by', 'archived_at'])]
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

        // Stored files live in a per-node directory — reclaim it when the node
        // is (permanently) deleted. Descendants are deleted through
        // deleteSubtree() so this hook fires for every file of a branch.
        static::deleted(function (self $node): void {
            if ($node->file_path !== null) {
                rescue(fn () => Storage::disk('local')->deleteDirectory(dirname($node->file_path)), report: false);
            }
        });
    }

    /**
     * Delete this node AND its whole branch model-by-model (deepest first) so
     * Eloquent events fire for every descendant — a bare delete() would let
     * the DB cascade wipe the rows without ever releasing their disk files.
     */
    public function deleteSubtree(): void
    {
        $byParent = static::where('board_id', $this->board_id)->get()->groupBy('parent_id');

        $stack = [$this];
        $ordered = [];

        while ($stack !== []) {
            $node = array_pop($stack);
            $ordered[] = $node;

            foreach ($byParent->get($node->id, collect()) as $child) {
                $stack[] = $child;
            }
        }

        foreach (array_reverse($ordered) as $node) {
            $node->delete();
        }
    }

    /**
     * Phosphor icon for the tree and listings, refined by mime for files.
     */
    public function iconName(): string
    {
        if ($this->type === self::TYPE_FOLDER) {
            return 'folder';
        }

        if ($this->type === self::TYPE_NOTE) {
            return 'file-text';
        }

        $mime = (string) $this->mime;

        return match (true) {
            str_starts_with($mime, 'image/') => 'file-image',
            str_starts_with($mime, 'video/') => 'file-video',
            str_starts_with($mime, 'audio/') => 'file-audio',
            $mime === 'application/pdf' => 'file-pdf',
            str_contains($mime, 'zip') || str_contains($mime, 'compressed') => 'file-zip',
            default => 'file',
        };
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
     * Delete nodes whose trash retention has elapsed, branch by branch (so
     * stored files are reclaimed). Called daily by the plugin's scheduled task.
     */
    public static function purgeExpiredTrash(): int
    {
        $expired = static::whereNotNull('archived_at')
            ->where('archived_at', '<', now()->subDays(self::TRASH_RETENTION_DAYS))
            ->get();

        foreach ($expired as $node) {
            if ($node->fresh() !== null) {
                $node->deleteSubtree();
            }
        }

        return $expired->count();
    }
}
