<div class="mx-auto max-w-3xl">
    <div class="flex items-center gap-2">
        <x-phosphor-trash class="h-6 w-6 text-neutral-400" />
        <h2 class="text-xl font-semibold tracking-tight">{{ __('shelf::shelf.trash') }}</h2>
    </div>
    <p class="mt-1 text-xs text-neutral-500 dark:text-neutral-400">
        {{ __('shelf::shelf.trash_hint', ['days' => \Board\PluginShelf\Models\ShelfNode::TRASH_RETENTION_DAYS]) }}
    </p>

    <div class="mt-4 space-y-1">
        @forelse ($trashedNodes as $node)
            <div class="flex items-center gap-2 rounded-lg border border-neutral-200 bg-white px-3 py-2 text-sm dark:border-neutral-800 dark:bg-neutral-900" wire:key="shelf-trash-{{ $node->id }}">
                <x-dynamic-component :component="'phosphor-'.$node->iconName()"
                    @class(['h-4 w-4 shrink-0', 'text-amber-500' => $node->isFolder(), 'text-indigo-500' => $node->type === \Board\PluginShelf\Models\ShelfNode::TYPE_NOTE, 'text-neutral-400' => $node->type === \Board\PluginShelf\Models\ShelfNode::TYPE_FILE]) />
                <span class="min-w-0 flex-1 truncate">{{ $node->name }}</span>
                <span class="text-xs whitespace-nowrap text-neutral-400 dark:text-neutral-500">{{ $node->archived_at->diffForHumans() }}</span>
                @if ($canWrite)
                    <button type="button" wire:click="restoreNode({{ $node->id }})"
                            class="inline-flex items-center gap-1 rounded-lg border border-neutral-200 px-2 py-1 text-xs text-neutral-600 hover:bg-neutral-100 dark:border-neutral-700 dark:text-neutral-300 dark:hover:bg-neutral-800">
                        <x-phosphor-arrow-counter-clockwise class="h-3.5 w-3.5" /> {{ __('shelf::shelf.restore') }}
                    </button>
                    <button type="button" wire:click="deleteForever({{ $node->id }})"
                            wire:confirm="{{ __('shelf::shelf.delete_forever_confirm', ['name' => $node->name]) }}"
                            class="inline-flex items-center gap-1 rounded-lg border border-red-200 px-2 py-1 text-xs text-red-600 hover:bg-red-50 dark:border-red-500/30 dark:text-red-400 dark:hover:bg-red-500/10">
                        <x-phosphor-x class="h-3.5 w-3.5" /> {{ __('shelf::shelf.delete_forever') }}
                    </button>
                @endif
            </div>
        @empty
            <p class="rounded-lg border border-dashed border-neutral-300 px-3 py-6 text-center text-xs text-neutral-400 dark:border-neutral-700 dark:text-neutral-500">{{ __('shelf::shelf.trash_empty') }}</p>
        @endforelse
    </div>
</div>
