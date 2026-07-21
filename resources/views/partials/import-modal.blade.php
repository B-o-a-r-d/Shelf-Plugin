{{-- Per-file import choices for a dropped batch (convert / unpack / store). --}}
<div class="fixed inset-0 z-[80] flex items-center justify-center bg-black/40 p-4" wire:key="shelf-import-modal">
    <div class="flex max-h-[80vh] w-full max-w-lg flex-col rounded-2xl border border-neutral-200 bg-white shadow-xl dark:border-neutral-700 dark:bg-neutral-900">
        <div class="flex items-center gap-2 border-b border-neutral-200 px-4 py-3 dark:border-neutral-800">
            <x-phosphor-tray-arrow-down class="h-5 w-5 text-indigo-500" />
            <h3 class="text-sm font-semibold">{{ __('shelf::shelf.import_title') }}</h3>
            <button type="button" wire:click="cancelImport" class="ml-auto rounded p-1 text-neutral-400 hover:bg-neutral-100 hover:text-neutral-600 dark:hover:bg-neutral-800"><x-phosphor-x class="h-4 w-4" /></button>
        </div>

        <div class="min-h-0 flex-1 space-y-2 overflow-y-auto p-4">
            <p class="text-xs text-neutral-500 dark:text-neutral-400">{{ __('shelf::shelf.import_hint') }}</p>

            @foreach ($uploads as $index => $upload)
                @php
                    $name = $upload->getClientOriginalName();
                    $ext = strtolower(pathinfo($name, PATHINFO_EXTENSION));
                    $convertible = \Board\PluginShelf\Support\Pandoc::convertible($name);
                    $isZip = $ext === 'zip';
                @endphp
                <div class="flex items-center gap-3 rounded-lg border border-neutral-200 px-3 py-2 dark:border-neutral-800" wire:key="shelf-import-{{ $index }}">
                    <x-phosphor-file class="h-4 w-4 shrink-0 text-neutral-400" />
                    <span class="min-w-0 flex-1 truncate text-sm" title="{{ $name }}">{{ $name }}</span>
                    <span class="text-xs whitespace-nowrap text-neutral-400">{{ $this->formatBytes((int) $upload->getSize()) }}</span>

                    @if ($convertible || $isZip)
                        <select wire:model="importChoices.{{ $index }}"
                                class="rounded-lg border border-neutral-300 bg-white px-2 py-1 text-xs shadow-sm focus:border-indigo-500 focus:outline-none dark:border-neutral-700 dark:bg-neutral-800">
                            @if ($convertible)
                                <option value="note">{{ __('shelf::shelf.import_as_note') }}</option>
                            @endif
                            @if ($isZip)
                                <option value="tree">{{ __('shelf::shelf.import_as_tree') }}</option>
                            @endif
                            <option value="file">{{ __('shelf::shelf.import_as_file') }}</option>
                        </select>
                    @else
                        <span class="text-xs whitespace-nowrap text-neutral-400">{{ __('shelf::shelf.import_as_file') }}</span>
                    @endif
                </div>
            @endforeach
        </div>

        <div class="flex items-center justify-end gap-2 border-t border-neutral-200 px-4 py-3 dark:border-neutral-800">
            <button type="button" wire:click="cancelImport" class="rounded-lg px-3 py-1.5 text-sm text-neutral-600 hover:bg-neutral-100 dark:text-neutral-300 dark:hover:bg-neutral-800">{{ __('shelf::shelf.cancel') }}</button>
            <button type="button" wire:click="confirmImport" wire:loading.attr="disabled"
                    class="rounded-lg bg-indigo-600 px-3 py-1.5 text-sm font-semibold text-white hover:bg-indigo-500 disabled:opacity-60">
                <span wire:loading.remove wire:target="confirmImport">{{ __('shelf::shelf.import_confirm') }}</span>
                <span wire:loading wire:target="confirmImport">{{ __('shelf::shelf.import_running') }}</span>
            </button>
        </div>
    </div>
</div>
