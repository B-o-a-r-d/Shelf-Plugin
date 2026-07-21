<?php

namespace Board\PluginShelf\Support;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Process;

/**
 * Thin wrapper around the pandoc binary for note imports (docx/odt/html/rtf →
 * markdown) and exports (markdown → docx/pdf). Availability is cached: when
 * the binary is missing the features degrade gracefully (greyed out in the UI)
 * instead of erroring — pandoc ships in the Docker image but a bare install
 * may not have it.
 */
final class Pandoc
{
    /** Import formats needing pandoc, keyed by file extension. */
    public const CONVERTIBLE = [
        'docx' => 'docx',
        'odt' => 'odt',
        'html' => 'html',
        'htm' => 'html',
        'rtf' => 'rtf',
    ];

    /** Formats readable without pandoc (plain text / already markdown). */
    public const PLAIN = ['md', 'markdown', 'txt'];

    private const PDF_ENGINES = ['pdflatex', 'xelatex', 'lualatex', 'wkhtmltopdf', 'weasyprint', 'tectonic'];

    public static function available(): bool
    {
        return (bool) Cache::remember('shelf:pandoc-available', now()->addHour(), function (): bool {
            try {
                return Process::timeout(10)->run(['pandoc', '--version'])->successful();
            } catch (\Throwable) {
                return false;
            }
        });
    }

    /**
     * PDF export needs pandoc AND a pdf engine (LaTeX or HTML-based).
     */
    public static function canExportPdf(): bool
    {
        return (bool) Cache::remember('shelf:pandoc-pdf', now()->addHour(), function (): bool {
            if (! self::available()) {
                return false;
            }

            foreach (self::PDF_ENGINES as $engine) {
                try {
                    if (Process::timeout(10)->run(['which', $engine])->successful()) {
                        return true;
                    }
                } catch (\Throwable) {
                    // Keep probing the remaining engines.
                }
            }

            return false;
        });
    }

    /**
     * Whether a filename can become a note (with or without pandoc).
     */
    public static function convertible(string $filename): bool
    {
        $ext = strtolower(pathinfo($filename, PATHINFO_EXTENSION));

        if (in_array($ext, self::PLAIN, true)) {
            return true;
        }

        return isset(self::CONVERTIBLE[$ext]) && self::available();
    }

    /**
     * Markdown content of an uploaded document. Plain formats are read
     * directly (with a latin-1 rescue for non-UTF-8 text files); the rest is
     * converted by pandoc to GFM.
     *
     * @throws \RuntimeException when the conversion fails
     */
    public static function toMarkdown(string $path, string $filename): string
    {
        $ext = strtolower(pathinfo($filename, PATHINFO_EXTENSION));

        if (in_array($ext, self::PLAIN, true)) {
            $content = (string) file_get_contents($path);

            return mb_check_encoding($content, 'UTF-8')
                ? $content
                : mb_convert_encoding($content, 'UTF-8', 'ISO-8859-1');
        }

        $format = self::CONVERTIBLE[$ext] ?? null;

        if ($format === null) {
            throw new \RuntimeException("Unsupported import format [{$ext}].");
        }

        $result = Process::timeout(120)->run(['pandoc', '-f', $format, '-t', 'gfm', '--wrap=none', $path]);

        if (! $result->successful()) {
            throw new \RuntimeException(trim($result->errorOutput()) ?: 'pandoc failed');
        }

        return $result->output();
    }

    /**
     * Convert markdown to docx or pdf, into a temp file whose path is
     * returned (caller streams it with deleteFileAfterSend).
     *
     * @throws \RuntimeException when the conversion fails
     */
    public static function fromMarkdown(string $markdown, string $format): string
    {
        if (! in_array($format, ['docx', 'pdf'], true)) {
            throw new \RuntimeException("Unsupported export format [{$format}].");
        }

        $in = tempnam(sys_get_temp_dir(), 'shelf-md-');
        $out = $in.'.'.$format;

        file_put_contents($in, $markdown);

        try {
            $result = Process::timeout(120)->run(['pandoc', '-f', 'gfm', '-o', $out, $in]);

            if (! $result->successful() || ! is_file($out)) {
                throw new \RuntimeException(trim($result->errorOutput()) ?: 'pandoc failed');
            }
        } finally {
            @unlink($in);
        }

        return $out;
    }
}
