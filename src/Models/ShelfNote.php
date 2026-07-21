<?php

namespace Board\PluginShelf\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * The markdown content of a note node, versioned for optimistic concurrency:
 * every save bumps `version`, and a save carrying a stale base version is
 * rejected (someone else saved meanwhile) instead of silently overwriting.
 */
#[Fillable(['node_id', 'markdown', 'version'])]
class ShelfNote extends Model
{
    /** Minimum age of the newest revision before a save snapshots a new one. */
    public const REVISION_INTERVAL_MINUTES = 10;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'version' => 'integer',
        ];
    }

    public function node(): BelongsTo
    {
        return $this->belongsTo(ShelfNode::class, 'node_id');
    }

    public function revisions(): HasMany
    {
        return $this->hasMany(ShelfNoteRevision::class, 'note_id')->orderByDesc('created_at');
    }

    /**
     * Snapshot the CURRENT content as a revision when the newest one is older
     * than the revision interval (Trilium-style periodic checkpoints), then
     * prune beyond the configured keep count. Call BEFORE overwriting.
     */
    public function maybeSnapshot(?int $userId, int $keep): bool
    {
        if (trim((string) $this->markdown) === '') {
            return false;
        }

        $latest = $this->revisions()->first();

        if ($latest !== null && $latest->created_at->gt(now()->subMinutes(self::REVISION_INTERVAL_MINUTES))) {
            return false;
        }

        $this->snapshot($userId, $keep);

        return true;
    }

    /**
     * Unconditionally snapshot the current content (used before a restore so
     * nothing is ever lost), then prune to the keep count.
     */
    public function snapshot(?int $userId, int $keep): void
    {
        $this->revisions()->create([
            'markdown' => (string) $this->markdown,
            'size' => strlen((string) $this->markdown),
            'created_by' => $userId,
            'created_at' => now(),
        ]);

        $this->revisions()
            ->orderByDesc('created_at')
            ->skip(max(1, $keep))
            ->take(PHP_INT_MAX)
            ->get()
            ->each
            ->delete();
    }

    /**
     * Total bytes this note weighs (current content + all revisions) — what
     * the owning node reports against the board quota.
     */
    public function weightBytes(): int
    {
        return strlen((string) $this->markdown) + (int) $this->revisions()->sum('size');
    }

    /**
     * Persist new content the standard way — periodic revision snapshot of the
     * previous state, version bump, node size refresh. Callers handle their
     * own concurrency/quota policy first (Livewire autosave, MCP tool).
     */
    public function persistContent(ShelfNode $node, string $markdown, ?int $userId, int $keep): bool
    {
        $snapshotted = $this->exists && $this->maybeSnapshot($userId, $keep);

        $this->markdown = $markdown;
        $this->version = ($this->exists ? $this->version : 0) + 1;
        $this->save();

        $node->update(['size' => $this->weightBytes()]);

        return $snapshotted;
    }
}
