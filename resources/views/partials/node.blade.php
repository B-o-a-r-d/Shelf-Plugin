@php
    $isFolder = $node->isFolder();
    $isNote = $node->type === \Board\PluginShelf\Models\ShelfNode::TYPE_NOTE;
    $children = $childrenByParent->get($node->id, collect());
@endphp

<div x-data="{ open: true, over: false }" wire:key="shelf-node-{{ $node->id }}">
    {{-- Insertion line: drop here to reorder the dragged node BEFORE this one,
         within this node's parent (Trilium-style between-siblings reorder). --}}
    @if ($canWrite)
        <div class="relative -my-0.5 h-1"
             @dragover.prevent.stop="dragId !== null && dragId !== {{ $node->id }} ? insBefore = {{ $node->id }} : null"
             @dragleave.stop="insBefore === {{ $node->id }} ? insBefore = null : null"
             @drop.prevent.stop="if (dragId !== null && dragId !== {{ $node->id }}) { $wire.reorderNode(dragId, {{ $node->parent_id ?? 'null' }}, {{ $node->id }}); } insBefore = null; dragId = null;">
            <div x-show="insBefore === {{ $node->id }}" x-cloak
                 class="pointer-events-none absolute inset-x-0 top-1/2 h-0.5 -translate-y-1/2 rounded bg-indigo-500"
                 style="margin-left: {{ 6 + $depth * 16 }}px"></div>
        </div>
    @endif
    <x-context-menu>
        <x-slot:trigger>
            <div
                data-shelf-node
                @class([
                    'group flex items-center gap-1 rounded-lg px-1.5 py-1 text-sm select-none',
                    'bg-indigo-50 text-indigo-800 dark:bg-indigo-500/10 dark:text-indigo-300' => $selectedNodeId === $node->id,
                    'text-neutral-700 hover:bg-neutral-200/70 dark:text-neutral-200 dark:hover:bg-neutral-800' => $selectedNodeId !== $node->id,
                ])
                :class="over ? 'ring-2 ring-indigo-400 ring-inset' : ''"
                style="padding-left: {{ 6 + $depth * 16 }}px"
                @if ($canWrite)
                    draggable="true"
                    @dragstart="dragId = {{ $node->id }}"
                    @dragend="dragId = null"
                    @if ($isFolder)
                        @dragover.prevent.stop="over = dragId !== null && dragId !== {{ $node->id }}"
                        @dragleave="over = false"
                        @drop.prevent.stop="over = false; if (dragId !== null && dragId !== {{ $node->id }}) { $wire.moveNode(dragId, {{ $node->id }}); dragId = null; }"
                    @endif
                @endif
            >
                @if ($isFolder)
                    <button type="button" @click.stop="open = ! open" class="rounded p-0.5 text-neutral-400 hover:text-neutral-600 dark:hover:text-neutral-300">
                        <x-phosphor-caret-right class="h-3 w-3 transition-transform" x-bind:class="open ? 'rotate-90' : ''" />
                    </button>
                @else
                    <span class="w-4"></span>
                @endif

                <x-dynamic-component :component="'phosphor-'.$node->iconName()"
                    @class(['h-4 w-4 shrink-0', 'text-amber-500' => $isFolder, 'text-indigo-500' => $isNote, 'text-neutral-400' => ! $isFolder && ! $isNote]) />

                @if ($renamingNodeId === $node->id)
                    <form wire:submit="renameNode" class="min-w-0 flex-1" @click.stop>
                        <input type="text" wire:model="renameValue" wire:keydown.escape="cancelRenaming"
                               x-init="$nextTick(() => { $el.focus(); $el.select(); })"
                               class="w-full rounded border border-indigo-400 bg-white px-1 py-0 text-sm focus:outline-none dark:bg-neutral-900">
                    </form>
                @else
                    <button type="button" wire:click="selectNode({{ $node->id }})" class="min-w-0 flex-1 truncate text-left">
                        {{ $node->name }}
                    </button>
                @endif
            </div>
        </x-slot:trigger>
        <x-slot:menu>
            @if ($canWrite)
                @if ($isFolder)
                    <x-context-menu.item icon="folder-plus" wire:click="startCreating('folder', {{ $node->id }})">{{ __('shelf::shelf.new_folder') }}</x-context-menu.item>
                    <x-context-menu.item icon="note-pencil" wire:click="startCreating('note', {{ $node->id }})">{{ __('shelf::shelf.new_note') }}</x-context-menu.item>
                    <x-context-menu.separator />
                @endif
                <x-context-menu.item icon="pencil-simple" wire:click="startRenaming({{ $node->id }})">{{ __('shelf::shelf.rename') }}</x-context-menu.item>
                @if ($node->parent_id !== null)
                    <x-context-menu.item icon="arrow-line-up" wire:click="moveNode({{ $node->id }}, null)">{{ __('shelf::shelf.move_to_root') }}</x-context-menu.item>
                @endif
                <x-context-menu.separator />
                <x-context-menu.item icon="trash" variant="danger" wire:click="trashNode({{ $node->id }})">{{ __('shelf::shelf.move_to_trash') }}</x-context-menu.item>
            @else
                <x-context-menu.item icon="eye" wire:click="selectNode({{ $node->id }})">{{ __('shelf::shelf.open') }}</x-context-menu.item>
            @endif
        </x-slot:menu>
    </x-context-menu>

    @error('renameValue')
        @if ($renamingNodeId === $node->id)
            <p class="px-2 text-xs text-red-600 dark:text-red-400" style="padding-left: {{ 26 + $depth * 16 }}px">{{ $message }}</p>
        @endif
    @enderror

    @if ($isFolder)
        <div x-show="open" x-cloak>
            @if ($creatingType !== null && $creatingParentId === $node->id)
                @include('shelf::partials.create-form', ['depth' => $depth + 1])
            @endif
            @foreach ($children as $child)
                @include('shelf::partials.node', ['node' => $child, 'depth' => $depth + 1])
            @endforeach
        </div>
    @endif
</div>
