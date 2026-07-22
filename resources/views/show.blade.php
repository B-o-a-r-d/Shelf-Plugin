<div class="-mb-8 flex h-[calc(100dvh-6rem)] flex-col" x-data="{ dragId: null }">
    {{-- Shelf's pre-built CSS + JS (compiled in dist/), served by the host and
         loaded once. Carries the plugin-specific Tailwind classes the host build
         purges, and registers the note editor's Alpine component. --}}
    <x-plugin-assets plugin="shelf" />

    @php
        // Same action-button recipe as the kanban board topbar (no-background variant).
        $topBtn = 'flex h-8 shrink-0 items-center justify-center rounded-lg border shadow-sm transition border-neutral-300 bg-white text-neutral-600 hover:bg-neutral-100 dark:border-neutral-700 dark:bg-neutral-800 dark:text-neutral-300 dark:hover:bg-neutral-700';
    @endphp

    {{-- Board topbar: slim full-bleed bar glued under the navbar, identical to
         the kanban board header — switcher left, presence + actions right. --}}
    <div class="relative z-30 -mx-4 -mt-8 mb-3 flex min-h-12 flex-wrap items-center gap-x-2 gap-y-1.5 border-b border-neutral-200 bg-white px-4 py-1.5 sm:-mx-6 sm:px-6 lg:-mx-8 lg:px-8 dark:border-neutral-800 dark:bg-neutral-900">
        <div class="flex min-w-0 flex-1 items-center gap-2">
            <div class="relative min-w-0" x-data="{ switcherOpen: false }" @keydown.escape.window="switcherOpen = false">
                <button type="button" @click="switcherOpen = ! switcherOpen" :aria-expanded="switcherOpen"
                        class="relative flex min-w-0 max-w-full items-center rounded-lg py-0.5 pl-2 pr-8 text-left transition hover:bg-neutral-100 dark:hover:bg-neutral-800">
                    <span class="flex min-w-0 flex-col leading-tight">
                        <span class="truncate text-base font-semibold tracking-tight sm:text-lg">{{ $board->name }}</span>
                        <span class="truncate text-[11px] font-medium text-neutral-500 dark:text-neutral-400">{{ $board->workspace->name }}</span>
                    </span>
                    <x-phosphor-caret-up-down class="absolute right-2 h-4 w-4 shrink-0 opacity-60"/>
                </button>

                <div x-show="switcherOpen" x-cloak x-transition
                     @click.outside="switcherOpen = false"
                     class="absolute left-0 top-full z-50 mt-1 w-64 rounded-xl border border-neutral-200 bg-white p-1 text-neutral-700 shadow-lg dark:border-neutral-700 dark:bg-neutral-900 dark:text-neutral-200">
                    <p class="truncate px-2 py-1.5 text-xs font-semibold uppercase tracking-wide text-neutral-400">{{ $board->workspace->name }}</p>
                    <div class="max-h-72 overflow-y-auto">
                        @foreach ($switcherBoards as $switchBoard)
                            @php
                                $switchType = $switchBoard->isKanban() ? null : (($switcherTypes ?? [])[$switchBoard->type] ?? false);
                            @endphp
                            @continue($switchType === false)
                            <a href="{{ $switchType === null ? route('boards.show', $switchBoard) : route($switchType['route'], $switchBoard) }}" wire:navigate @click="switcherOpen = false"
                               wire:key="shelf-switcher-{{ $switchBoard->id }}"
                               class="flex items-center gap-2 rounded-lg px-2 py-1.5 text-sm transition hover:bg-neutral-100 dark:hover:bg-neutral-800 {{ $switchBoard->id === $board->id ? 'font-medium text-indigo-600 dark:text-indigo-400' : '' }}">
                                @if ($switchType === null)
                                    <x-phosphor-kanban class="h-4 w-4 shrink-0 opacity-60"/>
                                @else
                                    <x-dynamic-component :component="'phosphor-'.$switchType['icon']" class="h-4 w-4 shrink-0 opacity-60"/>
                                @endif
                                <span class="min-w-0 flex-1 truncate">{{ $switchBoard->name }}</span>
                                @if ($switchBoard->id === $board->id)<x-phosphor-check class="h-4 w-4 shrink-0"/>@endif
                            </a>
                        @endforeach
                    </div>
                    <div class="mx-1 my-1 h-px bg-neutral-100 dark:bg-neutral-800"></div>
                    <a href="{{ route('dashboard') }}" wire:navigate @click="switcherOpen = false"
                       class="flex items-center gap-2 rounded-lg px-2 py-1.5 text-sm transition hover:bg-neutral-100 dark:hover:bg-neutral-800">
                        <x-phosphor-squares-four class="h-4 w-4 shrink-0 opacity-60"/> {{ __('Tous les tableaux') }}
                    </a>
                </div>
            </div>

            @unless ($canWrite)
                <span class="inline-flex shrink-0 items-center gap-1 rounded-full bg-neutral-200/80 px-2 py-0.5 text-[11px] font-medium text-neutral-600 dark:bg-neutral-800 dark:text-neutral-300" title="{{ __('Votre rôle est en lecture seule sur ce tableau.') }}">
                    <x-phosphor-eye class="h-3.5 w-3.5"/><span class="hidden sm:inline">{{ __('Lecture seule') }}</span>
                </span>
            @endunless
        </div>

        <div class="flex flex-wrap items-center gap-2">
            {{-- Presence: who is currently viewing this board --}}
            <div
                class="flex items-center -space-x-2"
                x-data='{
                    users: [],
                    init() {
                        if (! window.Echo) return;
                        const channel = "board-presence.{{ $board->id }}";
                        window.Echo.join(channel)
                            .here((u) => { this.users = u; })
                            .joining((u) => { this.users = [...this.users, u]; })
                            .leaving((u) => { this.users = this.users.filter((x) => x.id !== u.id); });
                        document.addEventListener("livewire:navigating", () => window.Echo.leave(channel), { once: true });
                    }
                }'
            >
                <template x-for="u in users" :key="u.id">
                    <div x-data="hoverCard(u)" @mouseenter="enter()" @mouseleave="leave()" class="relative inline-flex leading-none">
                        <template x-if="u.avatar_url">
                            <img :src="u.avatar_url" :alt="u.name" :title="u.name" draggable="false"
                                 class="h-8 w-8 rounded-full object-cover ring-2 ring-white dark:ring-neutral-950">
                        </template>
                        <template x-if="! u.avatar_url">
                            <span class="flex h-8 w-8 items-center justify-center rounded-full bg-indigo-100 text-xs font-semibold text-indigo-700 ring-2 ring-white dark:bg-indigo-500/20 dark:text-indigo-300 dark:ring-neutral-950"
                                  :title="u.name" x-text="u.name.charAt(0).toUpperCase()"></span>
                        </template>

                        <template x-teleport="body">
                            <template x-if="open">
                                <div x-transition @mouseenter="enter()" @mouseleave="leave()"
                                     :style="`top: ${coords.top}px; left: ${coords.left}px;`"
                                     class="fixed z-50 w-64 cursor-default rounded-xl border border-neutral-200/70 bg-white p-4 text-left shadow-lg dark:border-neutral-700 dark:bg-neutral-900">
                                    <div class="flex items-start gap-3">
                                        <template x-if="user.avatar_url">
                                            <img :src="user.avatar_url" :alt="user.name" class="h-10 w-10 shrink-0 rounded-full object-cover">
                                        </template>
                                        <template x-if="! user.avatar_url">
                                            <span class="flex h-10 w-10 shrink-0 items-center justify-center rounded-full bg-indigo-100 text-sm font-semibold text-indigo-700 dark:bg-indigo-500/20 dark:text-indigo-300"
                                                  x-text="user.name.charAt(0).toUpperCase()"></span>
                                        </template>
                                        <div class="min-w-0 flex-1">
                                            <p class="truncate font-semibold text-neutral-900 dark:text-neutral-100" x-text="user.name"></p>
                                            <template x-if="user.biography">
                                                <p class="mt-0.5 text-sm text-neutral-600 dark:text-neutral-300" x-text="user.biography"></p>
                                            </template>
                                            <template x-if="! user.biography">
                                                <p class="mt-0.5 text-sm italic text-neutral-400">{{ __('Pas de biographie.') }}</p>
                                            </template>
                                        </div>
                                    </div>
                                </div>
                            </template>
                        </template>
                    </div>
                </template>
            </div>
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
                    <button type="button" @click="open = ! open" class="{{ $topBtn }} w-8" title="{{ __('shelf::shelf.quota_configure') }}">
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
                    class="{{ $topBtn }} gap-1.5 px-2.5 text-sm {{ $showTrash ? '!border-indigo-300 !bg-indigo-50 !text-indigo-700 dark:!border-indigo-500/40 dark:!bg-indigo-500/10 dark:!text-indigo-300' : '' }}">
                <x-phosphor-trash class="h-4 w-4" />
                {{ __('shelf::shelf.trash') }}
                @if ($trashedNodes->isNotEmpty())
                    <span class="rounded-full bg-neutral-200 px-1.5 text-xs text-neutral-600 dark:bg-neutral-700 dark:text-neutral-300">{{ $trashedNodes->count() }}</span>
                @endif
            </button>
        </div>
    </div>

    <div class="mb-3 flex min-h-0 flex-1 overflow-hidden rounded-xl border border-neutral-200 bg-white shadow-sm dark:border-neutral-800 dark:bg-neutral-950">

        {{-- Tree --}}
        <aside class="flex w-72 shrink-0 flex-col border-r border-neutral-200 bg-neutral-50 dark:border-neutral-800 dark:bg-neutral-900/60">
            {{-- Search --}}
            <div class="relative border-b border-neutral-200 px-2 py-1.5 dark:border-neutral-800">
                <x-phosphor-magnifying-glass class="pointer-events-none absolute left-4 top-1/2 h-3.5 w-3.5 -translate-y-1/2 text-neutral-400" />
                <input type="search" wire:model.live.debounce.300ms="search" wire:keydown.escape="$set('search', '')"
                       placeholder="{{ __('shelf::shelf.search_placeholder') }}"
                       class="w-full rounded-lg border border-neutral-200 bg-white py-1 pl-7 pr-2 text-sm focus:border-indigo-500 focus:outline-none dark:border-neutral-700 dark:bg-neutral-800">
            </div>

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
                @if (mb_strlen(trim($search)) >= 2)
                    {{-- Search results replace the tree while a query is typed --}}
                    @forelse ($searchResults as $result)
                        <button type="button" wire:click="selectNode({{ $result['node']->id }})" wire:key="shelf-search-{{ $result['node']->id }}"
                                class="flex w-full items-start gap-2 rounded-lg px-2 py-1.5 text-left text-sm text-neutral-700 hover:bg-neutral-200/70 dark:text-neutral-200 dark:hover:bg-neutral-800">
                            <x-dynamic-component :component="'phosphor-'.$result['node']->iconName()"
                                @class(['mt-0.5 h-4 w-4 shrink-0', 'text-amber-500' => $result['node']->isFolder(), 'text-indigo-500' => $result['node']->type === \Board\PluginShelf\Models\ShelfNode::TYPE_NOTE, 'text-neutral-400' => $result['node']->type === \Board\PluginShelf\Models\ShelfNode::TYPE_FILE]) />
                            <span class="min-w-0 flex-1">
                                <span class="block truncate">{{ $result['node']->name }}</span>
                                @if ($result['snippet'] !== null)
                                    <span class="block truncate text-xs text-neutral-400 dark:text-neutral-500">{{ $result['snippet'] }}</span>
                                @endif
                            </span>
                        </button>
                    @empty
                        <p class="px-2 py-4 text-center text-xs text-neutral-400 dark:text-neutral-500">{{ __('shelf::shelf.search_no_results') }}</p>
                    @endforelse
                @else
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
                @endif
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

    @if ($showImportModal)
        @include('shelf::partials.import-modal')
    @endif
</div>
