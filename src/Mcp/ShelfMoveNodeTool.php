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

#[Description('Move a folder, note or file of a Shelf board into another folder (or to the root).')]
class ShelfMoveNodeTool extends Tool
{
    use ResolvesShelfNodes;

    public function handle(Request $request): Response
    {
        $request->validate([
            'node_id' => 'required|string',
            'parent_id' => 'nullable|string',
        ]);

        $node = $this->activeNode((string) $request->get('node_id'));

        if ($node === null) {
            return Response::error('Node not found or access denied.');
        }

        if (! $this->userCanWrite($node->board)) {
            return Response::error('You have a read-only role on this board.');
        }

        $target = null;

        if ($request->get('parent_id') !== null) {
            $target = $this->activeNode((string) $request->get('parent_id'));

            if ($target === null || ! $target->isFolder() || $target->board_id !== $node->board_id) {
                return Response::error('Destination folder not found on this board.');
            }

            // Never into itself or one of its descendants.
            for ($cursor = $target; $cursor !== null; $cursor = $cursor->parent) {
                if ($cursor->id === $node->id) {
                    return Response::error('Cannot move a folder into its own subtree.');
                }
            }
        }

        $node->update([
            'parent_id' => $target?->id,
            'position' => (int) ShelfNode::where('board_id', $node->board_id)->where('parent_id', $target?->id)->max('position') + 1,
        ]);

        ShelfActivity::log($node->board, 'shelf.node_moved', $node, ['via' => 'mcp', 'to' => $target?->name]);
        broadcast(new ShelfTreeUpdated($node->board_id));

        return Response::json(['id' => $node->public_id, 'parent' => $target?->public_id]);
    }

    /**
     * @return array<string, JsonSchema>
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'node_id' => $schema->string()->description('Public id (ULID) of the node to move.')->required(),
            'parent_id' => $schema->string()->description('Public id of the destination folder; omit for the root.'),
        ];
    }
}
