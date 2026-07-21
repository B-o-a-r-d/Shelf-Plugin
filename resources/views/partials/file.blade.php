@php
    $mime = (string) $selectedNode->mime;
    $fileUrl = route('shelf.file', $selectedNode);
    $isImage = str_starts_with($mime, 'image/');
    $isVideo = str_starts_with($mime, 'video/');
    $isAudio = str_starts_with($mime, 'audio/');
    $isPdf = $mime === 'application/pdf';
@endphp

<div class="mx-auto flex h-full max-w-4xl flex-col">
    <div class="flex flex-wrap items-center gap-3">
        <x-dynamic-component :component="'phosphor-'.$selectedNode->iconName()" class="h-6 w-6 shrink-0 text-neutral-400" />
        <h2 class="min-w-0 flex-1 truncate text-xl font-semibold tracking-tight">{{ $selectedNode->name }}</h2>
        <a href="{{ $fileUrl }}?dl=1"
           class="inline-flex items-center gap-1.5 rounded-lg border border-neutral-200 px-2.5 py-1.5 text-sm text-neutral-600 hover:bg-neutral-100 dark:border-neutral-700 dark:text-neutral-300 dark:hover:bg-neutral-800">
            <x-phosphor-download-simple class="h-4 w-4" /> {{ __('shelf::shelf.download') }}
        </a>
    </div>

    <p class="mt-1 text-xs text-neutral-500 dark:text-neutral-400">
        {{ $this->formatBytes($selectedNode->size) }}
        · {{ $selectedNode->created_at->translatedFormat('d M Y H:i') }}
        @if ($selectedNode->creator)
            · {{ __('shelf::shelf.uploaded_by', ['name' => $selectedNode->creator->name]) }}
        @endif
    </p>

    <div class="mt-4 min-h-0 flex-1">
        @if ($isImage)
            <img src="{{ $fileUrl }}" alt="{{ $selectedNode->name }}"
                 @click="$store.lightbox.open(@js([['type' => 'image', 'url' => $fileUrl, 'mime' => $mime]]), 0)"
                 class="max-h-full max-w-full cursor-zoom-in rounded-xl border border-neutral-200 object-contain dark:border-neutral-800">
        @elseif ($isPdf)
            <iframe src="{{ $fileUrl }}" title="{{ $selectedNode->name }}"
                    class="h-full min-h-[60vh] w-full rounded-xl border border-neutral-200 dark:border-neutral-800"></iframe>
        @elseif ($isVideo)
            <video controls preload="metadata" src="{{ $fileUrl }}" class="max-h-full w-full rounded-xl border border-neutral-200 bg-black dark:border-neutral-800"></video>
        @elseif ($isAudio)
            <audio controls src="{{ $fileUrl }}" class="w-full"></audio>
        @else
            <div class="flex flex-col items-center justify-center rounded-2xl border border-dashed border-neutral-300 p-10 text-center dark:border-neutral-700">
                <x-dynamic-component :component="'phosphor-'.$selectedNode->iconName()" class="h-12 w-12 text-neutral-300 dark:text-neutral-600" />
                <p class="mt-3 text-sm text-neutral-500 dark:text-neutral-400">{{ __('shelf::shelf.no_preview') }}</p>
                <a href="{{ $fileUrl }}?dl=1"
                   class="mt-3 inline-flex items-center gap-1.5 rounded-lg bg-indigo-600 px-3 py-1.5 text-sm font-medium text-white hover:bg-indigo-500">
                    <x-phosphor-download-simple class="h-4 w-4" /> {{ __('shelf::shelf.download') }}
                </a>
            </div>
        @endif
    </div>
</div>
