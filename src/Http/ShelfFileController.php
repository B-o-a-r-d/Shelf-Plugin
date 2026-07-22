<?php

namespace Board\PluginShelf\Http;

use Board\PluginShelf\Models\ShelfNode;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\Response;

/**
 * Streams a Shelf file node from the private disk, mirroring the host
 * MediaController's posture: board members only, nosniff + sandboxed CSP so a
 * malicious upload (SVG/HTML) can never script against the app origin.
 * Images, video, audio and pdf render inline (previews); anything else — or
 * ?dl=1 — downloads.
 */
class ShelfFileController
{
    public function __invoke(Request $request, ShelfNode $node): Response
    {
        Gate::authorize('view', $node->board);

        abort_unless($node->type === ShelfNode::TYPE_FILE && $node->file_path !== null, 404);

        $storage = Storage::disk('local');

        abort_unless($storage->exists($node->file_path), 404);

        $mime = $node->mime ?: ($storage->mimeType($node->file_path) ?: 'application/octet-stream');

        $inline = ! $request->boolean('dl') && (
            $mime === 'application/pdf'
            || str_starts_with($mime, 'image/')
            || str_starts_with($mime, 'video/')
            || str_starts_with($mime, 'audio/')
        );

        $name = str_replace(['"', "\r", "\n"], '', $node->name);

        $headers = [
            'X-Content-Type-Options' => 'nosniff',
            'Content-Type' => $mime,
            'Content-Disposition' => ($inline ? 'inline' : 'attachment').'; filename="'.$name.'"',
        ];

        // A sandboxed CSP disables the browser's built-in PDF viewer (toolbar,
        // zoom, search, print). A genuine application/pdf served inline with
        // `nosniff` can never be reinterpreted as active HTML, so the sandbox is
        // pointless for it — drop it there, keep it (defence in depth) elsewhere.
        if (! ($inline && $mime === 'application/pdf')) {
            $headers['Content-Security-Policy'] = "default-src 'none'; style-src 'unsafe-inline'; sandbox";
        }

        return response()->file($storage->path($node->file_path), $headers);
    }
}
