<?php

namespace Board\PluginShelf\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

/**
 * One comment on a note. A ROOT comment (parent_id null) anchors to a quoted
 * text span and carries the resolve state; REPLIES (parent_id set) chain to it
 * and have no anchor. Anchors re-resolve by searching the live note content.
 */
#[Fillable([
    'board_id', 'note_id', 'parent_id', 'anchor_quote', 'anchor_prefix',
    'anchor_start', 'body', 'created_by', 'resolved_at', 'resolved_by',
])]
class ShelfNoteComment extends Model
{
    protected $table = 'shelf_note_comments';

    protected static function booted(): void
    {
        static::creating(function (self $comment): void {
            if (empty($comment->public_id)) {
                $comment->public_id = (string) Str::ulid();
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
            'anchor_start' => 'integer',
            'resolved_at' => 'datetime',
        ];
    }

    public function note(): BelongsTo
    {
        return $this->belongsTo(ShelfNode::class, 'note_id');
    }

    public function parent(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_id');
    }

    public function replies(): HasMany
    {
        return $this->hasMany(self::class, 'parent_id')->orderBy('created_at');
    }

    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function resolver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'resolved_by');
    }

    public function isRoot(): bool
    {
        return $this->parent_id === null;
    }

    public function isResolved(): bool
    {
        return $this->resolved_at !== null;
    }
}
