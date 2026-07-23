<?php

namespace Board\PluginShelf\Mcp;

use Board\PluginShelf\Events\ShelfTreeUpdated;
use Board\PluginShelf\Mcp\Concerns\ResolvesShelfNodes;
use Board\PluginShelf\Support\ShelfActivity;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Tool;

#[Description('Permanently delete a Shelf node and its whole branch (files reclaimed from disk). Irreversible — the node must already be in the trash, so trash it first.')]
class ShelfDeleteNodeTool extends Tool
{
    use ResolvesShelfNodes;

    public function handle(Request $request): Response
    {
        $request->validate(['node_id' => 'required|string']);

        $node = $this->boardNode((string) $request->get('node_id'));

        if ($node === null) {
            return Response::error('Node not found or access denied.');
        }

        if (! $this->userCanWrite($node->board)) {
            return Response::error('You have a read-only role on this board.');
        }

        if (! $node->isTrashed()) {
            return Response::error('Only a trashed node can be permanently deleted — trash it first.');
        }

        $board = $node->board;
        $publicId = $node->public_id;

        ShelfActivity::log($board, 'shelf.node_deleted', $node, ['via' => 'mcp']);

        // Model-by-model (deepest first) so every stored file of the branch is
        // reclaimed from disk.
        $node->deleteSubtree();

        broadcast(new ShelfTreeUpdated($board->id));

        return Response::json(['id' => $publicId, 'deleted' => true]);
    }

    /**
     * @return array<string, JsonSchema>
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'node_id' => $schema->string()->description('Public id (ULID) of the trashed node to delete permanently.')->required(),
        ];
    }
}
