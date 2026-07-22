<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex, nofollow">
    <title>{{ $node->name }}</title>
    <style>
        :root {
            color-scheme: light dark;
            --bg: #ffffff;
            --fg: #1c1c1c;
            --muted: #6b7280;
            --border: #e5e7eb;
            --accent: #4f46e5;
            --code-bg: #f4f4f5;
        }
        @media (prefers-color-scheme: dark) {
            :root {
                --bg: #0a0a0a;
                --fg: #e5e5e5;
                --muted: #9ca3af;
                --border: #262626;
                --accent: #818cf8;
                --code-bg: #171717;
            }
        }
        * { box-sizing: border-box; }
        html, body { margin: 0; padding: 0; }
        body {
            background: var(--bg);
            color: var(--fg);
            font-family: ui-sans-serif, system-ui, -apple-system, "Segoe UI", Roboto, Helvetica, Arial, sans-serif;
            line-height: 1.65;
            -webkit-font-smoothing: antialiased;
        }
        .wrap { max-width: 46rem; margin: 0 auto; padding: 2.5rem 1.25rem 5rem; }
        header { border-bottom: 1px solid var(--border); padding-bottom: 1rem; margin-bottom: 2rem; }
        h1.note-title { font-size: 1.85rem; font-weight: 700; letter-spacing: -0.02em; margin: 0 0 0.35rem; }
        .meta { font-size: 0.8rem; color: var(--muted); }
        .prose { font-size: 1rem; }
        .prose h1 { font-size: 1.6rem; font-weight: 700; letter-spacing: -0.02em; margin: 2rem 0 0.75rem; }
        .prose h2 { font-size: 1.35rem; font-weight: 700; margin: 1.75rem 0 0.6rem; }
        .prose h3 { font-size: 1.15rem; font-weight: 600; margin: 1.5rem 0 0.5rem; }
        .prose p { margin: 0.9rem 0; }
        .prose a { color: var(--accent); text-decoration: underline; text-underline-offset: 2px; }
        .prose ul, .prose ol { padding-left: 1.5rem; margin: 0.9rem 0; }
        .prose li { margin: 0.3rem 0; }
        .prose blockquote {
            border-left: 3px solid var(--border);
            margin: 1rem 0; padding: 0.25rem 0 0.25rem 1rem;
            color: var(--muted);
        }
        .prose code {
            background: var(--code-bg);
            padding: 0.15em 0.4em; border-radius: 0.3rem;
            font-family: ui-monospace, SFMono-Regular, Menlo, Consolas, monospace;
            font-size: 0.875em;
        }
        .prose pre {
            background: var(--code-bg);
            padding: 1rem; border-radius: 0.6rem;
            overflow-x: auto; margin: 1.1rem 0;
        }
        .prose pre code { background: none; padding: 0; }
        .prose img { max-width: 100%; height: auto; border-radius: 0.5rem; }
        .prose hr { border: none; border-top: 1px solid var(--border); margin: 2rem 0; }
        .prose table { border-collapse: collapse; width: 100%; margin: 1.1rem 0; font-size: 0.925rem; }
        .prose th, .prose td { border: 1px solid var(--border); padding: 0.5rem 0.75rem; text-align: left; }
        .prose th { background: var(--code-bg); font-weight: 600; }
        footer {
            max-width: 46rem; margin: 0 auto; padding: 1.5rem 1.25rem;
            border-top: 1px solid var(--border);
            font-size: 0.78rem; color: var(--muted);
            display: flex; align-items: center; justify-content: space-between; gap: 1rem;
        }
        footer a { color: var(--muted); text-decoration: none; font-weight: 600; }
        footer a:hover { color: var(--fg); }
    </style>
</head>
<body>
    <div class="wrap">
        <header>
            <h1 class="note-title">{{ $node->name }}</h1>
            <p class="meta">
                {{ __('shelf::shelf.public_read_only') }}
                @if ($node->updated_at)
                    · {{ $node->updated_at->translatedFormat('d M Y') }}
                @endif
            </p>
        </header>
        <article class="prose">
            {!! $html !!}
        </article>
    </div>
    <footer>
        <span>{{ __('shelf::shelf.public_read_only') }}</span>
        <a href="{{ url('/') }}" rel="noopener">{{ config('app.name', 'Board') }}</a>
    </footer>
</body>
</html>
