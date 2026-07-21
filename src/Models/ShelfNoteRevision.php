<?php

namespace Board\PluginShelf\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * An immutable checkpoint of a note's markdown (append-only; pruned to the
 * configured keep count). created_at is set explicitly — no updated_at.
 */
#[Fillable(['note_id', 'markdown', 'size', 'created_by', 'created_at'])]
class ShelfNoteRevision extends Model
{
    public $timestamps = false;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'created_at' => 'datetime',
            'size' => 'integer',
        ];
    }

    public function note(): BelongsTo
    {
        return $this->belongsTo(ShelfNote::class, 'note_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
