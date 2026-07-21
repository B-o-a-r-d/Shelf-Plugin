<?php

namespace Board\PluginShelf\Mcp\Concerns;

use App\Models\Board;
use Board\PluginSdk\Contracts\PluginContext;
use Board\PluginShelf\Models\ShelfNode;
use Illuminate\Support\Facades\Auth;

/**
 * Shared resolution/authorization for Shelf MCP tools: boards by public id
 * through the SDK context (read access), nodes by public id scoped to their
 * board, writes through the host's contribute gate.
 */
trait ResolvesShelfNodes
{
    protected function shelfBoard(string $publicId): ?Board
    {
        if (! app(PluginContext::class)->userCanAccessBoard($publicId)) {
            return null;
        }

        $board = Board::where('public_id', $publicId)->first();

        return ($board !== null && $board->type === 'shelf') ? $board : null;
    }

    protected function activeNode(string $publicId): ?ShelfNode
    {
        $node = ShelfNode::where('public_id', $publicId)->whereNull('archived_at')->first();

        if ($node === null || $this->shelfBoard($node->board->public_id) === null) {
            return null;
        }

        return $node;
    }

    protected function userCanWrite(Board $board): bool
    {
        $user = Auth::user();

        return $user !== null && $user->can('contribute', $board);
    }
}
