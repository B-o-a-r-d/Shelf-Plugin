<?php

namespace Board\PluginShelf\Http;

use Board\PluginShelf\Models\ShelfNode;
use Board\PluginShelf\Models\ShelfNote;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;

/**
 * Exports a note as raw markdown. No converter binaries on purpose: plugins
 * must never require fiddling with the host's Docker image.
 */
class ShelfExportController
{
    public function __invoke(ShelfNode $node): Response
    {
        Gate::authorize('view', $node->board);

        abort_unless($node->type === ShelfNode::TYPE_NOTE, 404);

        $markdown = (string) ShelfNote::where('node_id', $node->id)->value('markdown');
        $filename = (Str::slug($node->name) ?: 'note').'.md';

        return response($markdown, 200, [
            'Content-Type' => 'text/markdown; charset=UTF-8',
            'X-Content-Type-Options' => 'nosniff',
            'Content-Disposition' => 'attachment; filename="'.$filename.'"',
        ]);
    }
}
