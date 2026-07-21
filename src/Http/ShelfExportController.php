<?php

namespace Board\PluginShelf\Http;

use Board\PluginShelf\Models\ShelfNode;
use Board\PluginShelf\Models\ShelfNote;
use Board\PluginShelf\Support\Pandoc;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;

/**
 * Exports a note: raw markdown always, docx/pdf through pandoc (404 when the
 * binary — or a pdf engine — is missing, mirroring the greyed-out UI).
 */
class ShelfExportController
{
    private const MIMES = [
        'md' => 'text/markdown; charset=UTF-8',
        'docx' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
        'pdf' => 'application/pdf',
    ];

    public function __invoke(ShelfNode $node, string $format): Response
    {
        Gate::authorize('view', $node->board);

        abort_unless($node->type === ShelfNode::TYPE_NOTE, 404);
        abort_unless(isset(self::MIMES[$format]), 404);

        if ($format === 'docx') {
            abort_unless(Pandoc::available(), 404);
        }

        if ($format === 'pdf') {
            abort_unless(Pandoc::canExportPdf(), 404);
        }

        $markdown = (string) ShelfNote::where('node_id', $node->id)->value('markdown');
        $filename = (Str::slug($node->name) ?: 'note').'.'.$format;

        if ($format === 'md') {
            return response($markdown, 200, [
                'Content-Type' => self::MIMES['md'],
                'X-Content-Type-Options' => 'nosniff',
                'Content-Disposition' => 'attachment; filename="'.$filename.'"',
            ]);
        }

        try {
            $path = Pandoc::fromMarkdown($markdown, $format);
        } catch (\RuntimeException $e) {
            report($e);

            abort(422, __('shelf::shelf.export_failed'));
        }

        return response()
            ->download($path, $filename, [
                'Content-Type' => self::MIMES[$format],
                'X-Content-Type-Options' => 'nosniff',
            ])
            ->deleteFileAfterSend(true);
    }
}
