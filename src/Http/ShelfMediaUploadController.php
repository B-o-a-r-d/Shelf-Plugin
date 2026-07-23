<?php

namespace Board\PluginShelf\Http;

use App\Models\Board;
use Board\PluginShelf\Models\ShelfBoard;
use Board\PluginShelf\Models\ShelfMedia;
use Board\PluginShelf\Models\ShelfNode;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;

/**
 * Receives an image pasted/dropped/picked in the note editor, stores it on the
 * private disk (counted against the board quota) and returns its serve URL for
 * the markdown ![](url). Images only (no SVG — no active content); contribute
 * role required.
 */
class ShelfMediaUploadController
{
    /** Per-image cap in kilobytes (25 MB) — inline images should stay light. */
    private const MAX_KB = 25600;

    public function __invoke(Request $request): JsonResponse
    {
        $request->validate([
            'board' => 'required|string',
            'note' => 'nullable|string',
            'file' => 'required|file|mimes:jpg,jpeg,png,gif,webp,avif|max:'.self::MAX_KB,
        ]);

        $board = Board::where('public_id', (string) $request->input('board'))->first();

        abort_if($board === null || $board->type !== 'shelf', 404);

        Gate::authorize('contribute', $board);

        $note = null;

        if ($request->filled('note')) {
            $note = ShelfNode::where('public_id', (string) $request->input('note'))
                ->where('board_id', $board->id)
                ->first();
        }

        $upload = $request->file('file');
        $size = (int) $upload->getSize();

        if (ShelfNode::usedBytes($board) + $size > ShelfBoard::quotaBytesFor($board)) {
            return response()->json(['error' => __('shelf::shelf.quota_exceeded')], 422);
        }

        $media = ShelfMedia::create([
            'board_id' => $board->id,
            'note_id' => $note?->id,
            'mime' => $upload->getMimeType(),
            'size' => $size,
            'created_by' => Auth::id(),
        ]);

        $safeName = preg_replace('/[^\w.\- ]+/u', '_', $upload->getClientOriginalName()) ?: 'image';
        $path = $upload->storeAs('shelf/'.$board->public_id.'/media/'.$media->public_id, $safeName, 'local');

        $media->update(['path' => $path]);

        return response()->json(['url' => route('shelf.media', $media)]);
    }
}
