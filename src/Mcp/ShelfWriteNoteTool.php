<?php

namespace Board\PluginShelf\Mcp;

use Board\PluginShelf\Events\ShelfTreeUpdated;
use Board\PluginShelf\Mcp\Concerns\ResolvesShelfNodes;
use Board\PluginShelf\Models\ShelfBoard;
use Board\PluginShelf\Models\ShelfNode;
use Board\PluginShelf\Models\ShelfNote;
use Board\PluginShelf\ShelfPlugin;
use Board\PluginShelf\Support\ShelfActivity;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Support\Facades\Auth;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Tool;

#[Description('Replace the markdown content of a note on a Shelf board (the previous content is kept as a revision).')]
class ShelfWriteNoteTool extends Tool
{
    use ResolvesShelfNodes;

    public function handle(Request $request): Response
    {
        $request->validate([
            'note_id' => 'required|string',
            'markdown' => 'present|string',
        ]);

        $node = $this->activeNode((string) $request->get('note_id'));

        if ($node === null || $node->type !== ShelfNode::TYPE_NOTE) {
            return Response::error('Note not found or access denied.');
        }

        if (! $this->userCanWrite($node->board)) {
            return Response::error('You have a read-only role on this board.');
        }

        $markdown = (string) $request->get('markdown');
        $note = ShelfNote::firstOrNew(['node_id' => $node->id]);

        $delta = strlen($markdown) - strlen((string) $note->markdown);

        if ($delta > 0 && ShelfNode::usedBytes($node->board) + $delta > ShelfBoard::quotaBytesFor($node->board)) {
            return Response::error('The board storage quota would be exceeded.');
        }

        $note->persistContent($node, $markdown, Auth::id(), ShelfPlugin::revisionsKeep());

        ShelfActivity::log($node->board, 'shelf.note_edited', $node, ['via' => 'mcp']);
        broadcast(new ShelfTreeUpdated($node->board_id));

        return Response::json(['id' => $node->public_id, 'version' => $note->version]);
    }

    /**
     * @return array<string, JsonSchema>
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'note_id' => $schema->string()->description('The note node public id (ULID).')->required(),
            'markdown' => $schema->string()->description('The full new markdown content of the note.')->required(),
        ];
    }
}
