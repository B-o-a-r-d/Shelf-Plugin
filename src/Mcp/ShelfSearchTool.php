<?php

namespace Board\PluginShelf\Mcp;

use Board\PluginShelf\Mcp\Concerns\ResolvesShelfNodes;
use Board\PluginShelf\Models\ShelfNode;
use Board\PluginShelf\Support\ShelfSearch;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Tool;

#[Description('Search a Shelf board: matches folder/note/file names (accent-insensitive) and note contents (full-text). Returns matching nodes with a snippet for content hits.')]
class ShelfSearchTool extends Tool
{
    use ResolvesShelfNodes;

    public function handle(Request $request): Response
    {
        $request->validate([
            'board_id' => 'required|string',
            'query' => 'required|string|min:2',
        ]);

        $board = $this->shelfBoard((string) $request->get('board_id'));

        if ($board === null) {
            return Response::error('Board not found or access denied.');
        }

        $query = trim((string) $request->get('query'));

        $nodes = ShelfNode::where('board_id', $board->id)
            ->whereNull('archived_at')
            ->orderBy('name')
            ->get();

        /** @var array<int, array{node: ShelfNode, snippet: string|null}> $hits */
        $hits = [];

        foreach ($nodes as $node) {
            if (ShelfSearch::nameMatches($node->name, $query)) {
                $hits[$node->id] = ['node' => $node, 'snippet' => null];
            }
        }

        $noteIds = $nodes->where('type', ShelfNode::TYPE_NOTE)->pluck('id');

        foreach (ShelfSearch::noteContents($noteIds, $query) as $match) {
            $node = $nodes->firstWhere('id', $match->node_id);

            if ($node === null) {
                continue;
            }

            $hits[$node->id] = [
                'node' => $node,
                'snippet' => $hits[$node->id]['snippet'] ?? ShelfSearch::snippet((string) $match->markdown, $query),
            ];
        }

        $results = array_map(fn (array $hit): array => [
            'id' => $hit['node']->public_id,
            'type' => $hit['node']->type,
            'name' => $hit['node']->name,
            'snippet' => $hit['snippet'],
        ], array_slice(array_values($hits), 0, 30));

        return Response::json(['count' => count($results), 'results' => $results]);
    }

    /**
     * @return array<string, JsonSchema>
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'board_id' => $schema->string()->description('Public id (ULID) of the Shelf board to search.')->required(),
            'query' => $schema->string()->description('Search text (min 2 chars). Matches names and note content, accent-insensitive.')->required(),
        ];
    }
}
