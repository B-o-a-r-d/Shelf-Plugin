<div class="-mb-8 flex h-[calc(100dvh-6rem)] min-h-0 flex-col overflow-hidden" x-data="{ dragId: null }">

    {{-- Header --}}
    <div class="flex flex-wrap items-center gap-4 border-b border-neutral-200 bg-white px-4 py-3 dark:border-neutral-800 dark:bg-neutral-900">
        <a href="{{ route('dashboard') }}" wire:navigate class="inline-flex items-center gap-1 text-sm text-neutral-500 hover:text-neutral-700 dark:text-neutral-400 dark:hover:text-neutral-200">
            <x-phosphor-arrow-left class="h-4 w-4" /> {{ __('shelf::shelf.back_to_dashboard') }}
        </a>

        <div class="flex min-w-0 items-center gap-2">
            <x-phosphor-books class="h-5 w-5 shrink-0 text-indigo-500" />
            <h1 class="truncate text-lg font-semibold tracking-tight">{{ $board->name }}</h1>
            <span class="hidden truncate text-sm text-neutral-500 sm:block dark:text-neutral-400">— {{ $board->workspace->name }}</span>
        </div>

        <div class="ml-auto flex items-center gap-3">
            {{-- Quota gauge --}}
            <div class="flex items-center gap-2" title="{{ __('shelf::shelf.quota_usage', ['used' => $this->formatBytes($usedBytes), 'quota' => $this->formatBytes($quotaBytes)]) }}">
                <x-phosphor-hard-drives class="h-4 w-4 text-neutral-400" />
                <div class="h-2 w-28 overflow-hidden rounded-full bg-neutral-200 dark:bg-neutral-700">
                    <div class="h-full rounded-full {{ $usagePercent >= 90 ? 'bg-red-500' : ($usagePercent >= 70 ? 'bg-amber-500' : 'bg-indigo-500') }}" style="width: {{ max(2, $usagePercent) }}%"></div>
                </div>
                <span class="text-xs whitespace-nowrap text-neutral-500 dark:text-neutral-400">{{ $this->formatBytes($usedBytes) }} / {{ $this->formatBytes($quotaBytes) }}</span>
            </div>

            {{-- Quota override (board admins) --}}
            @if ($canManage)
                <div x-data="{ open: false }" class="relative">
                    <button type="button" @click="open = ! open" class="rounded p-1.5 text-neutral-400 hover:bg-neutral-100 hover:text-neutral-700 dark:hover:bg-neutral-800 dark:hover:text-neutral-200" title="{{ __('shelf::shelf.quota_configure') }}">
                        <x-phosphor-gear class="h-4 w-4" />
                    </button>
                    <div x-show="open" x-cloak @click.outside="open = false" x-transition.opacity.duration.100ms
                         class="absolute right-0 z-40 mt-2 w-64 rounded-lg border border-neutral-200 bg-white p-3 text-sm shadow-lg dark:border-neutral-700 dark:bg-neutral-800">
                        <p class="font-medium">{{ __('shelf::shelf.quota_override') }}</p>
                        <p class="mt-1 text-xs text-neutral-500 dark:text-neutral-400">{{ __('shelf::shelf.quota_override_help', ['default' => \Board\PluginShelf\ShelfPlugin::defaultQuotaGb()]) }}</p>
                        <form wire:submit="saveQuota" class="mt-2 flex items-center gap-2">
                            <input type="number" min="1" max="10000" wire:model="quotaInput" placeholder="{{ \Board\PluginShelf\ShelfPlugin::defaultQuotaGb() }}"
                                   class="w-20 rounded-lg border border-neutral-300 bg-white px-2 py-1 text-sm shadow-sm focus:border-indigo-500 focus:outline-none dark:border-neutral-600 dark:bg-neutral-700">
                            <span class="text-xs text-neutral-500 dark:text-neutral-400">{{ __('shelf::shelf.gb') }}</span>
                            <button type="submit" class="ml-auto rounded-lg bg-indigo-600 px-2.5 py-1 text-xs font-medium text-white hover:bg-indigo-500">{{ __('shelf::shelf.save') }}</button>
                        </form>
                        @error('quotaInput') <p class="mt-1 text-xs text-red-600 dark:text-red-400">{{ $message }}</p> @enderror
                    </div>
                </div>
            @endif

            {{-- Trash toggle --}}
            <button type="button" wire:click="$toggle('showTrash')"
                    class="inline-flex items-center gap-1.5 rounded-lg border px-2.5 py-1.5 text-sm {{ $showTrash ? 'border-indigo-300 bg-indigo-50 text-indigo-700 dark:border-indigo-500/40 dark:bg-indigo-500/10 dark:text-indigo-300' : 'border-neutral-200 text-neutral-600 hover:bg-neutral-100 dark:border-neutral-700 dark:text-neutral-300 dark:hover:bg-neutral-800' }}">
                <x-phosphor-trash class="h-4 w-4" />
                {{ __('shelf::shelf.trash') }}
                @if ($trashedNodes->isNotEmpty())
                    <span class="rounded-full bg-neutral-200 px-1.5 text-xs text-neutral-600 dark:bg-neutral-700 dark:text-neutral-300">{{ $trashedNodes->count() }}</span>
                @endif
            </button>
        </div>
    </div>

    <div class="flex min-h-0 flex-1">

        {{-- Tree --}}
        <aside class="flex w-72 shrink-0 flex-col border-r border-neutral-200 bg-neutral-50 dark:border-neutral-800 dark:bg-neutral-900/60">
            @if ($canWrite)
                <div class="flex items-center gap-1 border-b border-neutral-200 px-2 py-1.5 dark:border-neutral-800">
                    <button type="button" wire:click="startCreating('folder')" class="inline-flex items-center gap-1 rounded px-2 py-1 text-xs text-neutral-600 hover:bg-neutral-200 dark:text-neutral-300 dark:hover:bg-neutral-800">
                        <x-phosphor-folder-plus class="h-4 w-4" /> {{ __('shelf::shelf.new_folder') }}
                    </button>
                    <button type="button" wire:click="startCreating('note')" class="inline-flex items-center gap-1 rounded px-2 py-1 text-xs text-neutral-600 hover:bg-neutral-200 dark:text-neutral-300 dark:hover:bg-neutral-800">
                        <x-phosphor-note-pencil class="h-4 w-4" /> {{ __('shelf::shelf.new_note') }}
                    </button>
                </div>
            @endif

            {{-- Root drop zone: the whole tree pane accepts a drop to move to root --}}
            <div class="min-h-0 flex-1 overflow-y-auto p-2"
                 @if ($canWrite)
                     @dragover.prevent
                     @drop.prevent="if (dragId !== null) { $wire.moveNode(dragId, null); dragId = null; }"
                 @endif
            >
                @if ($creatingType !== null && $creatingParentId === null)
                    @include('shelf::partials.create-form')
                @endif

                @forelse ($childrenByParent->get(null, collect()) as $node)
                    @include('shelf::partials.node', ['node' => $node, 'depth' => 0])
                @empty
                    @if ($creatingType === null)
                        <p class="px-2 py-4 text-center text-xs text-neutral-400 dark:text-neutral-500">{{ __('shelf::shelf.tree_empty') }}</p>
                    @endif
                @endforelse
            </div>
        </aside>

        {{-- Main panel --}}
        @php $noteOpen = ! $showTrash && $selectedNode?->type === \Board\PluginShelf\Models\ShelfNode::TYPE_NOTE; @endphp
        <main class="min-w-0 flex-1 bg-white dark:bg-neutral-950 {{ $noteOpen ? 'flex flex-col overflow-hidden' : 'overflow-y-auto p-6' }}">
            @if ($showTrash)
                @include('shelf::partials.trash')
            @elseif ($selectedNode === null)
                <div class="flex h-full flex-col items-center justify-center text-center">
                    <x-phosphor-books class="h-12 w-12 text-neutral-300 dark:text-neutral-700" />
                    <p class="mt-3 text-sm font-medium text-neutral-500 dark:text-neutral-400">{{ __('shelf::shelf.empty_state_title') }}</p>
                    <p class="mt-1 max-w-sm text-xs text-neutral-400 dark:text-neutral-500">{{ __('shelf::shelf.empty_state_hint') }}</p>
                    @if ($canWrite)
                        <div class="mt-6 w-full max-w-md">
                            @include('shelf::partials.upload-zone')
                        </div>
                    @endif
                </div>
            @elseif ($selectedNode->isFolder())
                <div class="mx-auto max-w-3xl">
                    <div class="flex items-center gap-2">
                        <x-phosphor-folder class="h-6 w-6 text-amber-500" />
                        <h2 class="text-xl font-semibold tracking-tight">{{ $selectedNode->name }}</h2>
                    </div>
                    <div class="mt-4 space-y-1">
                        @forelse ($childrenByParent->get($selectedNode->id, collect()) as $child)
                            <button type="button" wire:click="selectNode({{ $child->id }})"
                                    class="flex w-full items-center gap-2 rounded-lg border border-neutral-200 bg-white px-3 py-2 text-left text-sm hover:border-indigo-300 dark:border-neutral-800 dark:bg-neutral-900 dark:hover:border-indigo-500/40">
                                <x-dynamic-component :component="'phosphor-'.$child->iconName()"
                                    @class(['h-4 w-4 shrink-0', 'text-amber-500' => $child->isFolder(), 'text-indigo-500' => $child->type === \Board\PluginShelf\Models\ShelfNode::TYPE_NOTE, 'text-neutral-400' => $child->type === \Board\PluginShelf\Models\ShelfNode::TYPE_FILE]) />
                                <span class="min-w-0 flex-1 truncate">{{ $child->name }}</span>
                                @if ($child->type === \Board\PluginShelf\Models\ShelfNode::TYPE_FILE)
                                    <span class="text-xs text-neutral-400 dark:text-neutral-500">{{ $this->formatBytes($child->size) }}</span>
                                @endif
                            </button>
                        @empty
                            <p class="rounded-lg border border-dashed border-neutral-300 px-3 py-6 text-center text-xs text-neutral-400 dark:border-neutral-700 dark:text-neutral-500">{{ __('shelf::shelf.folder_empty') }}</p>
                        @endforelse
                    </div>

                    @if ($canWrite)
                        <div class="mt-4">
                            @include('shelf::partials.upload-zone')
                        </div>
                    @endif
                </div>
            @elseif ($noteOpen)
                @include('shelf::partials.note')
            @else
                @include('shelf::partials.file')
            @endif
        </main>
    </div>
</div>
