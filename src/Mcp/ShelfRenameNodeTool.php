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

#[Description('Rename a folder, note or file of a Shelf board.')]
class ShelfRenameNodeTool extends Tool
{
    use ResolvesShelfNodes;

    public function handle(Request $request): Response
    {
        $request->validate([
            'node_id' => 'required|string',
            'name' => 'required|string|max:255',
        ]);

        $node = $this->activeNode((string) $request->get('node_id'));

        if ($node === null) {
            return Response::error('Node not found or access denied.');
        }

        if (! $this->userCanWrite($node->board)) {
            return Response::error('You have a read-only role on this board.');
        }

        $from = $node->name;
        $node->update(['name' => trim((string) $request->get('name'))]);

        ShelfActivity::log($node->board, 'shelf.node_renamed', $node, ['via' => 'mcp', 'from' => $from]);
        broadcast(new ShelfTreeUpdated($node->board_id));

        return Response::json(['id' => $node->public_id, 'name' => $node->name]);
    }

    /**
     * @return array<string, JsonSchema>
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'node_id' => $schema->string()->description('Public id (ULID) of the node to rename.')->required(),
            'name' => $schema->string()->description('New name.')->required(),
        ];
    }
}
