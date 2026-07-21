@php $depth = $depth ?? 0; @endphp

<form wire:submit="createNode" class="my-0.5 flex items-center gap-1.5 rounded-lg px-1.5 py-1" style="padding-left: {{ 10 + $depth * 16 }}px">
    @if ($creatingType === \Board\PluginShelf\Models\ShelfNode::TYPE_FOLDER)
        <x-phosphor-folder class="h-4 w-4 shrink-0 text-amber-500" />
    @else
        <x-phosphor-file-text class="h-4 w-4 shrink-0 text-indigo-500" />
    @endif
    <input type="text" wire:model="newNodeName" wire:keydown.escape="cancelCreating"
           x-init="$nextTick(() => $el.focus())"
           placeholder="{{ $creatingType === \Board\PluginShelf\Models\ShelfNode::TYPE_FOLDER ? __('shelf::shelf.folder_name_placeholder') : __('shelf::shelf.note_name_placeholder') }}"
           class="min-w-0 flex-1 rounded border border-indigo-400 bg-white px-1.5 py-0.5 text-sm focus:outline-none dark:bg-neutral-900">
</form>
@error('newNodeName')
    <p class="px-2 text-xs text-red-600 dark:text-red-400" style="padding-left: {{ 30 + $depth * 16 }}px">{{ $message }}</p>
@enderror
