<?php

namespace Board\PluginShelf\Mcp;

use Board\PluginShelf\Events\ShelfTreeUpdated;
use Board\PluginShelf\Mcp\Concerns\ResolvesShelfNodes;
use Board\PluginShelf\Models\ShelfBoard;
use Board\PluginShelf\Models\ShelfNode;
use Board\PluginShelf\Models\ShelfNote;
use Board\PluginShelf\Support\ShelfActivity;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Support\Facades\Auth;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Tool;

#[Description('Create a folder or a markdown note on a Shelf board, at the root or inside a folder.')]
class ShelfCreateNodeTool extends Tool
{
    use ResolvesShelfNodes;

    public function handle(Request $request): Response
    {
        $request->validate([
            'board_id' => 'required|string',
            'type' => 'required|in:folder,note',
            'name' => 'required|string|max:255',
            'parent_id' => 'nullable|string',
            'markdown' => 'nullable|string',
        ]);

        $board = $this->shelfBoard((string) $request->get('board_id'));

        if ($board === null) {
            return Response::error('Shelf board not found or access denied.');
        }

        if (! $this->userCanWrite($board)) {
            return Response::error('You have a read-only role on this board.');
        }

        $parent = null;

        if ($request->get('parent_id') !== null) {
            $parent = $this->activeNode((string) $request->get('parent_id'));

            if ($parent === null || ! $parent->isFolder() || $parent->board_id !== $board->id) {
                return Response::error('Parent folder not found on this board.');
            }
        }

        $markdown = (string) ($request->get('markdown') ?? '');

        if ($markdown !== '' && ShelfNode::usedBytes($board) + strlen($markdown) > ShelfBoard::quotaBytesFor($board)) {
            return Response::error('The board storage quota would be exceeded.');
        }

        $node = ShelfNode::create([
            'board_id' => $board->id,
            'parent_id' => $parent?->id,
            'type' => (string) $request->get('type'),
            'name' => trim((string) $request->get('name')),
            'position' => (int) ShelfNode::where('board_id', $board->id)->where('parent_id', $parent?->id)->max('position') + 1,
            'size' => $request->get('type') === ShelfNode::TYPE_NOTE ? strlen($markdown) : 0,
            'created_by' => Auth::id(),
        ]);

        if ($node->type === ShelfNode::TYPE_NOTE) {
            ShelfNote::create(['node_id' => $node->id, 'markdown' => $markdown, 'version' => 1]);
        }

        ShelfActivity::log($board, 'shelf.node_created', $node, ['via' => 'mcp']);
        broadcast(new ShelfTreeUpdated($board->id));

        return Response::json(['id' => $node->public_id, 'type' => $node->type, 'name' => $node->name]);
    }

    /**
     * @return array<string, JsonSchema>
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'board_id' => $schema->string()->description('The Shelf board public id (ULID).')->required(),
            'type' => $schema->string()->enum(['folder', 'note'])->description('What to create.')->required(),
            'name' => $schema->string()->description('Name of the folder, or title of the note.')->required(),
            'parent_id' => $schema->string()->description('Public id of the destination folder; omit for the root.'),
            'markdown' => $schema->string()->description('Initial markdown content when creating a note.'),
        ];
    }
}
