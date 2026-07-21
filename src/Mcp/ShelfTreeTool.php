<?php

namespace Board\PluginShelf\Mcp;

use Board\PluginShelf\Mcp\Concerns\ResolvesShelfNodes;
use Board\PluginShelf\Models\ShelfNode;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Support\Collection;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Tool;

#[Description('List the full folder/note/file tree of a Shelf board (the document shelf board type).')]
class ShelfTreeTool extends Tool
{
    use ResolvesShelfNodes;

    public function handle(Request $request): Response
    {
        $request->validate(['board_id' => 'required|string']);

        $board = $this->shelfBoard((string) $request->get('board_id'));

        if ($board === null) {
            return Response::error('Shelf board not found or access denied.');
        }

        $byParent = ShelfNode::where('board_id', $board->id)
            ->whereNull('archived_at')
            ->orderBy('position')
            ->get()
            ->groupBy('parent_id');

        $build = function (?int $parentId) use (&$build, $byParent): array {
            return $byParent->get($parentId, new Collection)->map(fn (ShelfNode $node): array => [
                'id' => $node->public_id,
                'type' => $node->type,
                'name' => $node->name,
                'size' => $node->size,
                'children' => $node->type === ShelfNode::TYPE_FOLDER ? $build($node->id) : [],
            ])->values()->all();
        };

        return Response::json(['board' => $board->name, 'tree' => $build(null)]);
    }

    /**
     * @return array<string, JsonSchema>
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'board_id' => $schema->string()->description('The Shelf board public id (ULID).')->required(),
        ];
    }
}
