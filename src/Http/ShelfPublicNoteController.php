<?php

namespace Board\PluginShelf\Http;

use Board\PluginShelf\Models\ShelfNode;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Str;

/**
 * Public, auth-free read-only page of a shared note, resolved by its random
 * share token. The markdown is rendered server-side with raw HTML stripped and
 * unsafe links disallowed, so a note can never smuggle active content into the
 * app origin. Trashed notes and revoked tokens 404.
 */
class ShelfPublicNoteController
{
    public function __invoke(string $token): View
    {
        $node = ShelfNode::query()
            ->with('note')
            ->where('type', ShelfNode::TYPE_NOTE)
            ->where('share_token', $token)
            ->whereNull('archived_at')
            ->first();

        abort_if($node === null, 404);

        $html = Str::markdown((string) $node->note?->markdown, [
            'html_input' => 'strip',
            'allow_unsafe_links' => false,
        ]);

        return view('shelf::public-note', [
            'node' => $node,
            'html' => $html,
        ]);
    }
}
