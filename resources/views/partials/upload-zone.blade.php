{{-- Multi-file dropzone bound to $uploads / saveUploads. Reuses the host's
     'dropzone' Alpine component (drag state, progress, previews). --}}
<div
    x-data="dropzone"
    @dragover.prevent="dragging = true"
    @dragleave.prevent="dragging = false"
    @drop.prevent="onDrop($event)"
    x-on:livewire-upload-start="uploading = true; progress = 0"
    x-on:livewire-upload-progress="progress = $event.detail.progress"
    x-on:livewire-upload-finish="uploading = false; clearPreview(); $wire.saveUploads()"
    x-on:livewire-upload-error="uploading = false; error = '{{ __('shelf::shelf.upload_failed') }}'"
>
    <input type="file" x-ref="input" multiple @change="onSelect()" wire:model="uploads" class="hidden">

    <button
        type="button"
        @click="browse()"
        class="flex w-full flex-col items-center justify-center gap-1.5 rounded-xl border-2 border-dashed px-4 py-6 text-center transition"
        :class="dragging
            ? 'border-indigo-500 bg-indigo-50 dark:border-indigo-400 dark:bg-indigo-500/10'
            : 'border-neutral-300 hover:border-indigo-400 hover:bg-neutral-50 dark:border-neutral-700 dark:hover:border-indigo-500 dark:hover:bg-neutral-800/50'"
    >
        <x-phosphor-cloud-arrow-up class="h-7 w-7 text-neutral-400" />
        <span class="text-sm font-medium text-neutral-700 dark:text-neutral-200">
            <span x-text="dragging ? '{{ __('shelf::shelf.upload_drop') }}' : '{{ __('shelf::shelf.upload_drag') }}'"></span>
            <span x-show="! dragging" class="text-indigo-600 dark:text-indigo-400">{{ __('shelf::shelf.upload_browse') }}</span>
        </span>
        <span class="text-xs text-neutral-400">{{ __('shelf::shelf.upload_hint') }}</span>
    </button>

    <div x-show="uploading" x-cloak class="mt-2 h-1.5 w-full overflow-hidden rounded-full bg-neutral-200 dark:bg-neutral-700">
        <div class="h-full rounded-full bg-indigo-500 transition-all" :style="`width: ${progress}%`"></div>
    </div>

    <p x-show="error" x-cloak x-text="error" class="mt-2 text-xs text-red-600 dark:text-red-400"></p>

    @if ($uploadError !== null)
        <p class="mt-2 text-xs text-red-600 dark:text-red-400">{{ $uploadError }}</p>
    @endif
    @error('uploads.*')
        <p class="mt-2 text-xs text-red-600 dark:text-red-400">{{ $message }}</p>
    @enderror
</div>
