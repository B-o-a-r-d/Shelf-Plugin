<?php

namespace Board\PluginShelf\Http;

use Board\PluginShelf\Models\ShelfMedia;
use Board\PluginShelf\Models\ShelfNode;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\Response;

/**
 * Serves an inline note image. Access mirrors the note's: board members can
 * always view it; anonymous visitors can view it only when the note it belongs
 * to is publicly shared (so a shared note's images render on the public page).
 * nosniff + sandboxed CSP so a crafted upload can never script the app origin.
 */
class ShelfMediaController
{
    public function __invoke(ShelfMedia $media): Response
    {
        $note = $media->note;

        $publiclyShared = $note !== null
            && $note->type === ShelfNode::TYPE_NOTE
            && $note->archived_at === null
            && $note->share_token !== null;

        if (! $publiclyShared) {
            $user = Auth::user();

            abort_if($user === null || ! Gate::forUser($user)->allows('view', $media->board), 403);
        }

        $storage = Storage::disk('local');

        abort_unless($storage->exists($media->path), 404);

        return response()->file($storage->path($media->path), [
            'X-Content-Type-Options' => 'nosniff',
            'Content-Type' => $media->mime ?: 'application/octet-stream',
            'Content-Security-Policy' => "default-src 'none'; style-src 'unsafe-inline'; sandbox",
            'Cache-Control' => 'private, max-age=3600',
        ]);
    }
}
