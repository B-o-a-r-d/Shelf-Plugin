<?php

namespace Board\PluginShelf\Mcp;

use Board\PluginShelf\Mcp\Concerns\ResolvesShelfNodes;
use Board\PluginShelf\Models\ShelfNode;
use Board\PluginShelf\Models\ShelfNote;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Tool;

#[Description('Read the markdown content of a note on a Shelf board.')]
class ShelfReadNoteTool extends Tool
{
    use ResolvesShelfNodes;

    public function handle(Request $request): Response
    {
        $request->validate(['note_id' => 'required|string']);

        $node = $this->activeNode((string) $request->get('note_id'));

        if ($node === null || $node->type !== ShelfNode::TYPE_NOTE) {
            return Response::error('Note not found or access denied.');
        }

        $note = ShelfNote::firstWhere('node_id', $node->id);

        return Response::json([
            'id' => $node->public_id,
            'name' => $node->name,
            'markdown' => (string) $note?->markdown,
            'version' => $note?->version ?? 0,
        ]);
    }

    /**
     * @return array<string, JsonSchema>
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'note_id' => $schema->string()->description('The note node public id (ULID), as returned by the tree tool.')->required(),
        ];
    }
}
