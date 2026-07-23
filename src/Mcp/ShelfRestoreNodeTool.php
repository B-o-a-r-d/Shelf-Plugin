<?php

namespace Board\PluginShelf\Mcp;

use Board\PluginShelf\Events\ShelfTreeUpdated;
use Board\PluginShelf\Mcp\Concerns\ResolvesShelfNodes;
use Board\PluginShelf\Models\ShelfNode;
use Board\PluginShelf\Support\ShelfActivity;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Tool;

#[Description('Restore a trashed folder, note or file of a Shelf board. Restores the whole branch; if the original parent is gone or still trashed, the node comes back at the root.')]
class ShelfRestoreNodeTool extends Tool
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
            return Response::error('This node is not in the trash.');
        }

        $parent = $node->parent;
        $parentId = ($parent !== null && ! $parent->isTrashed()) ? $parent->id : null;

        $node->update([
            'archived_at' => null,
            'parent_id' => $parentId,
            'position' => (int) ShelfNode::where('board_id', $node->board_id)->where('parent_id', $parentId)->max('position') + 1,
        ]);

        ShelfActivity::log($node->board, 'shelf.node_restored', $node, ['via' => 'mcp']);
        broadcast(new ShelfTreeUpdated($node->board_id));

        return Response::json(['id' => $node->public_id, 'parent' => $node->parent?->public_id]);
    }

    /**
     * @return array<string, JsonSchema>
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'node_id' => $schema->string()->description('Public id (ULID) of the trashed node to restore.')->required(),
        ];
    }
}
