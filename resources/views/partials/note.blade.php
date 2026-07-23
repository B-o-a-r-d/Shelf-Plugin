{{-- Note panel: TipTap editor (autosave + optimistic versioning), presence,
     revision history. The editor wrapper is wire:ignore — Livewire renders it
     once per node (wire:key) and Alpine owns everything inside. --}}
@php
    $tbBtn = 'flex h-7 min-w-7 items-center justify-center rounded px-1.5 text-sm text-neutral-600 hover:bg-neutral-100 dark:text-neutral-300 dark:hover:bg-neutral-700';
@endphp

<div class="relative flex min-h-0 flex-1">

    {{-- Editor column --}}
    <div class="flex min-w-0 flex-1 flex-col"
         wire:ignore
         wire:key="note-editor-{{ $selectedNode->id }}"
         x-data="shelfNoteEditor(@js([
             'nodeId' => $selectedNode->id,
             'noteName' => $selectedNode->name,
             'boardId' => $board->public_id,
             'notePublicId' => $selectedNode->public_id,
             'uploadUrl' => route('shelf.media.upload'),
             'csrf' => csrf_token(),
             'markdown' => (string) $note?->markdown,
             'version' => $note?->version ?? 0,
             'canWrite' => $canWrite,
             'userId' => auth()->id(),
             'userName' => auth()->user()->name,
             'i18n' => [
                 'saving' => __('shelf::shelf.editor_saving'),
                 'saved' => __('shelf::shelf.editor_saved'),
                 'dirty' => __('shelf::shelf.editor_dirty'),
                 'editing' => __('shelf::shelf.presence_editing'),
                 'linkPrompt' => __('shelf::shelf.link_prompt'),
             ],
             'slashItems' => [
                 ['command' => 'h1', 'label' => __('shelf::shelf.slash_h1'), 'hint' => '#'],
                 ['command' => 'h2', 'label' => __('shelf::shelf.slash_h2'), 'hint' => '##'],
                 ['command' => 'h3', 'label' => __('shelf::shelf.slash_h3'), 'hint' => '###'],
                 ['command' => 'bullet', 'label' => __('shelf::shelf.slash_bullet'), 'hint' => '-'],
                 ['command' => 'ordered', 'label' => __('shelf::shelf.slash_ordered'), 'hint' => '1.'],
                 ['command' => 'quote', 'label' => __('shelf::shelf.slash_quote'), 'hint' => '>'],
                 ['command' => 'code', 'label' => __('shelf::shelf.slash_code'), 'hint' => '```'],
                 ['command' => 'table', 'label' => __('shelf::shelf.slash_table'), 'hint' => '|-|'],
                 ['command' => 'hr', 'label' => __('shelf::shelf.slash_hr'), 'hint' => '---'],
             ],
         ]))">

        {{-- Note header --}}
        <div class="flex items-center gap-3 border-b border-neutral-200 px-4 py-2.5 dark:border-neutral-800">
            <x-phosphor-file-text class="h-5 w-5 shrink-0 text-indigo-500" />
            <h2 class="min-w-0 truncate text-base font-semibold tracking-tight">{{ $selectedNode->name }}</h2>

            {{-- Presence: others viewing/editing this note --}}
            <div class="flex items-center gap-1" x-show="others().length > 0" x-cloak>
                <template x-for="user in others()" :key="user.id">
                    <img :src="user.avatar_url" :title="user.name" class="h-6 w-6 rounded-full ring-2 ring-white dark:ring-neutral-950" />
                </template>
                <span class="ml-1 text-xs text-indigo-500 dark:text-indigo-400" x-show="editingNames().length > 0" x-text="i18n.editing.replace(':names', editingNames().join(', '))"></span>
            </div>

            <div class="ml-auto flex items-center gap-2">
                {{-- Save status --}}
                <span class="text-xs text-neutral-400 dark:text-neutral-500" x-show="canWrite" x-cloak>
                    <span x-show="status === 'saving'">{{ __('shelf::shelf.editor_saving') }}</span>
                    <span x-show="status === 'dirty'">{{ __('shelf::shelf.editor_dirty') }}</span>
                    <span x-show="status === 'saved' && savedAt !== null" x-text="i18n.saved.replace(':time', savedAt ?? '')"></span>
                </span>

                {{-- Public share: Alpine-driven so it works inside this wire:ignore
                     subtree — toggleShare() returns the new {shared,url}, we read it
                     off the $wire promise and update locally (no Livewire DOM patch
                     ever reaches here). --}}
                @if ($canWrite)
                    <div class="relative"
                         x-data="{
                             open: false,
                             busy: false,
                             copied: false,
                             error: false,
                             shared: @js($selectedNode->isShared()),
                             url: @js($selectedNode->publicUrl()),
                             async toggle() {
                                 if (this.busy) { return; }
                                 this.busy = true; this.error = false; this.copied = false;
                                 try {
                                     const r = await $wire.toggleShare({{ $selectedNode->id }});
                                     this.shared = r.shared;
                                     this.url = r.url;
                                 } catch (e) {
                                     this.error = true;
                                 } finally {
                                     this.busy = false;
                                 }
                             },
                             async copy() {
                                 if (! this.url) { return; }
                                 try {
                                     await navigator.clipboard.writeText(this.url);
                                 } catch (e) {
                                     const i = this.$refs.shareInput;
                                     if (i) { i.focus(); i.select(); document.execCommand('copy'); }
                                 }
                                 this.copied = true;
                                 setTimeout(() => this.copied = false, 1500);
                             },
                         }"
                         @keydown.escape.window="open = false">
                        <button type="button" @click="open = ! open"
                                :class="shared ? 'border-emerald-300 bg-emerald-50 text-emerald-700 dark:border-emerald-500/40 dark:bg-emerald-500/10 dark:text-emerald-300' : 'border-neutral-200 text-neutral-600 hover:bg-neutral-100 dark:border-neutral-700 dark:text-neutral-300 dark:hover:bg-neutral-800'"
                                class="inline-flex items-center gap-1.5 rounded-lg border px-2 py-1 text-xs">
                            <x-phosphor-share-network class="h-3.5 w-3.5" /> {{ __('shelf::shelf.share') }}
                        </button>

                        <div x-show="open" x-cloak x-transition
                             @click.outside="open = false"
                             class="absolute right-0 z-50 mt-1.5 w-72 rounded-xl border border-neutral-200 bg-white p-3 shadow-lg dark:border-neutral-700 dark:bg-neutral-800">
                            <p class="text-xs font-semibold text-neutral-700 dark:text-neutral-200">{{ __('shelf::shelf.share_title') }}</p>
                            <p class="mt-0.5 text-[11px] text-neutral-500 dark:text-neutral-400">{{ __('shelf::shelf.share_hint') }}</p>

                            {{-- Busy indicator (visibility on the backend round-trip) --}}
                            <div x-show="busy" x-cloak class="mt-2.5 flex items-center gap-2 text-[11px] text-neutral-500 dark:text-neutral-400">
                                <x-phosphor-circle-notch class="h-3.5 w-3.5 animate-spin" /> {{ __('shelf::shelf.share_working') }}
                            </div>
                            <p x-show="error" x-cloak class="mt-2.5 text-[11px] text-red-600 dark:text-red-400">{{ __('shelf::shelf.share_error') }}</p>

                            {{-- Shared state: URL + copy + revoke --}}
                            <div x-show="! busy && shared" x-cloak>
                                <div class="mt-2.5 flex items-center gap-1.5">
                                    <input type="text" readonly :value="url" x-ref="shareInput" @focus="$el.select()"
                                           class="min-w-0 flex-1 truncate rounded-lg border border-neutral-200 bg-neutral-50 px-2 py-1.5 text-[11px] text-neutral-600 dark:border-neutral-700 dark:bg-neutral-900 dark:text-neutral-300">
                                    <button type="button" @click="copy()"
                                            class="inline-flex shrink-0 items-center gap-1 rounded-lg bg-indigo-600 px-2 py-1.5 text-[11px] font-medium text-white hover:bg-indigo-500">
                                        <span x-show="! copied" class="inline-flex items-center gap-1"><x-phosphor-copy class="h-3.5 w-3.5" /> {{ __('shelf::shelf.share_copy') }}</span>
                                        <span x-show="copied" x-cloak class="inline-flex items-center gap-1"><x-phosphor-check class="h-3.5 w-3.5" /> {{ __('shelf::shelf.share_copied') }}</span>
                                    </button>
                                </div>
                                <button type="button" @click="toggle()"
                                        class="mt-2 inline-flex w-full items-center justify-center gap-1.5 rounded-lg border border-red-200 px-2 py-1.5 text-[11px] font-medium text-red-600 hover:bg-red-50 dark:border-red-500/30 dark:text-red-400 dark:hover:bg-red-500/10">
                                    <x-phosphor-link-break class="h-3.5 w-3.5" /> {{ __('shelf::shelf.share_revoke') }}
                                </button>
                            </div>

                            {{-- Not shared yet: enable --}}
                            <button type="button" x-show="! busy && ! shared" @click="toggle()"
                                    class="mt-2.5 inline-flex w-full items-center justify-center gap-1.5 rounded-lg bg-indigo-600 px-2 py-1.5 text-[11px] font-medium text-white hover:bg-indigo-500">
                                <x-phosphor-link class="h-3.5 w-3.5" /> {{ __('shelf::shelf.share_enable') }}
                            </button>
                        </div>
                    </div>
                @endif

                {{-- Split preview: markdown source (left) + rendered HTML (right) --}}
                <button type="button" @click="togglePreview()"
                        :class="preview ? 'border-indigo-300 bg-indigo-50 text-indigo-700 dark:border-indigo-500/40 dark:bg-indigo-500/10 dark:text-indigo-300' : 'border-neutral-200 text-neutral-600 hover:bg-neutral-100 dark:border-neutral-700 dark:text-neutral-300 dark:hover:bg-neutral-800'"
                        class="inline-flex h-7 w-7 items-center justify-center rounded-lg border" title="{{ __('shelf::shelf.preview_split') }}">
                    <x-phosphor-square-split-horizontal class="h-4 w-4" />
                </button>

                {{-- Exports: markdown (route) + PDF (client-side print, no infra dep) --}}
                <a href="{{ route('shelf.export', $selectedNode) }}"
                   class="inline-flex h-7 w-7 items-center justify-center rounded-lg border border-neutral-200 text-neutral-600 hover:bg-neutral-100 dark:border-neutral-700 dark:text-neutral-300 dark:hover:bg-neutral-800"
                   title="{{ __('shelf::shelf.export_md') }}">
                    <x-phosphor-file-md class="h-4 w-4" />
                </a>
                <button type="button" @click="exportPdf()"
                        class="inline-flex h-7 w-7 items-center justify-center rounded-lg border border-neutral-200 text-neutral-600 hover:bg-neutral-100 dark:border-neutral-700 dark:text-neutral-300 dark:hover:bg-neutral-800"
                        title="{{ __('shelf::shelf.export_pdf') }}">
                    <x-phosphor-file-pdf-duotone class="h-4 w-4" />
                </button>

                <button type="button" wire:click="toggleComments"
                        class="inline-flex items-center gap-1.5 rounded-lg border px-2 py-1 text-xs {{ $showComments ? 'border-indigo-300 bg-indigo-50 text-indigo-700 dark:border-indigo-500/40 dark:bg-indigo-500/10 dark:text-indigo-300' : 'border-neutral-200 text-neutral-600 hover:bg-neutral-100 dark:border-neutral-700 dark:text-neutral-300 dark:hover:bg-neutral-800' }}">
                    <x-phosphor-chat-circle-text class="h-3.5 w-3.5" /> {{ __('shelf::shelf.comments') }}
                    @if ($comments->isNotEmpty())
                        <span class="rounded-full bg-neutral-200 px-1.5 text-[10px] text-neutral-600 dark:bg-neutral-700 dark:text-neutral-300">{{ $comments->count() }}</span>
                    @endif
                </button>

                <button type="button" wire:click="toggleHistory"
                        class="inline-flex items-center gap-1.5 rounded-lg border px-2 py-1 text-xs {{ $showHistory ? 'border-indigo-300 bg-indigo-50 text-indigo-700 dark:border-indigo-500/40 dark:bg-indigo-500/10 dark:text-indigo-300' : 'border-neutral-200 text-neutral-600 hover:bg-neutral-100 dark:border-neutral-700 dark:text-neutral-300 dark:hover:bg-neutral-800' }}">
                    <x-phosphor-clock-counter-clockwise class="h-3.5 w-3.5" /> {{ __('shelf::shelf.history') }}
                </button>
            </div>
        </div>

        {{-- Conflict / quota banners --}}
        <div x-show="status === 'conflict'" x-cloak class="flex items-center gap-3 border-b border-amber-200 bg-amber-50 px-4 py-2 text-sm text-amber-800 dark:border-amber-500/30 dark:bg-amber-500/10 dark:text-amber-300">
            <x-phosphor-warning class="h-4 w-4 shrink-0" />
            <span class="min-w-0 flex-1">{{ __('shelf::shelf.conflict_message') }}</span>
            <button type="button" @click="reload()" class="rounded-lg border border-amber-300 px-2 py-1 text-xs hover:bg-amber-100 dark:border-amber-500/40 dark:hover:bg-amber-500/20">{{ __('shelf::shelf.conflict_reload') }}</button>
            <button type="button" @click="overwrite()" class="rounded-lg bg-amber-600 px-2 py-1 text-xs font-medium text-white hover:bg-amber-500">{{ __('shelf::shelf.conflict_overwrite') }}</button>
        </div>
        <div x-show="status === 'quota'" x-cloak class="flex items-center gap-3 border-b border-red-200 bg-red-50 px-4 py-2 text-sm text-red-700 dark:border-red-500/30 dark:bg-red-500/10 dark:text-red-400">
            <x-phosphor-warning-octagon class="h-4 w-4 shrink-0" />
            <span>{{ __('shelf::shelf.quota_exceeded') }}</span>
        </div>

        {{-- Toolbar (stays available in split mode — the left pane is editable) --}}
        @if ($canWrite)
            <div class="flex flex-wrap items-center gap-0.5 border-b border-neutral-200 px-2 py-1 dark:border-neutral-800">
                <button type="button" @click="run('toggleBold')" :class="isActive('bold') && 'bg-neutral-200 dark:bg-neutral-700'" class="{{ $tbBtn }} font-bold" title="{{ __('shelf::shelf.tb_bold') }}">B</button>
                <button type="button" @click="run('toggleItalic')" :class="isActive('italic') && 'bg-neutral-200 dark:bg-neutral-700'" class="{{ $tbBtn }} italic" title="{{ __('shelf::shelf.tb_italic') }}">I</button>
                <button type="button" @click="run('toggleStrike')" :class="isActive('strike') && 'bg-neutral-200 dark:bg-neutral-700'" class="{{ $tbBtn }} line-through" title="{{ __('shelf::shelf.tb_strike') }}">S</button>
                <span class="mx-1 h-5 w-px bg-neutral-200 dark:bg-neutral-700"></span>
                <button type="button" @click="run('toggleHeading', { level: 1 })" :class="isActive('heading', { level: 1 }) && 'bg-neutral-200 dark:bg-neutral-700'" class="{{ $tbBtn }} font-semibold" title="{{ __('shelf::shelf.slash_h1') }}">H1</button>
                <button type="button" @click="run('toggleHeading', { level: 2 })" :class="isActive('heading', { level: 2 }) && 'bg-neutral-200 dark:bg-neutral-700'" class="{{ $tbBtn }} font-semibold" title="{{ __('shelf::shelf.slash_h2') }}">H2</button>
                <button type="button" @click="run('toggleHeading', { level: 3 })" :class="isActive('heading', { level: 3 }) && 'bg-neutral-200 dark:bg-neutral-700'" class="{{ $tbBtn }} font-semibold" title="{{ __('shelf::shelf.slash_h3') }}">H3</button>
                <span class="mx-1 h-5 w-px bg-neutral-200 dark:bg-neutral-700"></span>
                <button type="button" @click="run('toggleBulletList')" :class="isActive('bulletList') && 'bg-neutral-200 dark:bg-neutral-700'" class="{{ $tbBtn }}" title="{{ __('shelf::shelf.slash_bullet') }}"><x-phosphor-list-bullets class="h-4 w-4" /></button>
                <button type="button" @click="run('toggleOrderedList')" :class="isActive('orderedList') && 'bg-neutral-200 dark:bg-neutral-700'" class="{{ $tbBtn }}" title="{{ __('shelf::shelf.slash_ordered') }}"><x-phosphor-list-numbers class="h-4 w-4" /></button>
                <button type="button" @click="run('toggleBlockquote')" :class="isActive('blockquote') && 'bg-neutral-200 dark:bg-neutral-700'" class="{{ $tbBtn }}" title="{{ __('shelf::shelf.slash_quote') }}"><x-phosphor-quotes class="h-4 w-4" /></button>
                <button type="button" @click="run('toggleCodeBlock')" :class="isActive('codeBlock') && 'bg-neutral-200 dark:bg-neutral-700'" class="{{ $tbBtn }}" title="{{ __('shelf::shelf.slash_code') }}"><x-phosphor-code class="h-4 w-4" /></button>
                <button type="button" @click="toggleLink()" :class="isActive('link') && 'bg-neutral-200 dark:bg-neutral-700'" class="{{ $tbBtn }}" title="{{ __('shelf::shelf.tb_link') }}"><x-phosphor-link class="h-4 w-4" /></button>
                <button type="button" @click="run('insertTable', { rows: 3, cols: 3, withHeaderRow: true })" :class="isActive('table') && 'bg-neutral-200 dark:bg-neutral-700'" class="{{ $tbBtn }}" title="{{ __('shelf::shelf.slash_table') }}"><x-phosphor-table class="h-4 w-4" /></button>
                <button type="button" @click="pickImage()" class="{{ $tbBtn }}" title="{{ __('shelf::shelf.tb_image') }}"><x-phosphor-image class="h-4 w-4" /></button>

                {{-- Table controls, only while the caret is inside a table --}}
                <template x-if="isActive('table')">
                    <span class="flex items-center gap-0.5">
                        <span class="mx-1 h-5 w-px bg-neutral-200 dark:bg-neutral-700"></span>
                        <button type="button" @click="run('addRowAfter')" class="{{ $tbBtn }}" title="{{ __('shelf::shelf.tb_table_add_row') }}"><x-phosphor-rows class="h-4 w-4" /></button>
                        <button type="button" @click="run('addColumnAfter')" class="{{ $tbBtn }}" title="{{ __('shelf::shelf.tb_table_add_col') }}"><x-phosphor-columns class="h-4 w-4" /></button>
                        <button type="button" @click="run('deleteRow')" class="{{ $tbBtn }} text-red-500" title="{{ __('shelf::shelf.tb_table_del_row') }}"><x-phosphor-rows class="h-4 w-4 rotate-180" /></button>
                        <button type="button" @click="run('deleteColumn')" class="{{ $tbBtn }} text-red-500" title="{{ __('shelf::shelf.tb_table_del_col') }}"><x-phosphor-columns class="h-4 w-4 rotate-180" /></button>
                        <button type="button" @click="run('deleteTable')" class="{{ $tbBtn }} text-red-500" title="{{ __('shelf::shelf.tb_table_delete') }}"><x-phosphor-trash class="h-4 w-4" /></button>
                    </span>
                </template>

                <span class="ml-auto hidden text-[11px] text-neutral-400 sm:block dark:text-neutral-500">{{ __('shelf::shelf.slash_hint') }}</span>
            </div>
        @endif

        {{-- Editor + optional live source pane. In split mode the TipTap editor
             stays fully editable on the left; the generated markdown source
             tracks it live on the right, with proportional scroll-follow. --}}
        <div class="flex min-h-0 flex-1 divide-x divide-neutral-200 overflow-hidden dark:divide-neutral-800">
            <div class="min-w-0 flex-1 overflow-y-auto" x-ref="editorScroll" @scroll="syncScroll('src')">
                <div class="js-note-mount min-h-full" x-ignore></div>
            </div>
            <pre x-show="preview" x-cloak x-ref="pvOut" @scroll="syncScroll('out')"
                 class="m-0 min-w-0 flex-1 overflow-y-auto whitespace-pre-wrap break-words bg-neutral-50 px-4 py-4 font-mono text-xs leading-5 text-neutral-700 dark:bg-neutral-900/40 dark:text-neutral-300"
                 x-text="previewMd"></pre>
        </div>

        {{-- Slash-command menu --}}
        <template x-teleport="body">
            <div x-show="slash.open" x-cloak
                 :style="`top: ${slash.y}px; left: ${slash.x}px;`"
                 class="fixed z-[70] w-56 rounded-lg border border-neutral-200 bg-white p-1 text-sm shadow-lg dark:border-neutral-700 dark:bg-neutral-800">
                <template x-for="(item, idx) in filteredSlash()" :key="item.command">
                    <button type="button"
                            @click="applySlash(item)"
                            @mouseenter="slash.index = idx"
                            :class="slash.index === idx ? 'bg-indigo-50 text-indigo-700 dark:bg-indigo-500/10 dark:text-indigo-300' : 'text-neutral-700 dark:text-neutral-200'"
                            class="flex w-full items-center gap-2 rounded px-2 py-1.5 text-left">
                        <span class="w-8 shrink-0 rounded bg-neutral-100 px-1 text-center font-mono text-[10px] text-neutral-500 dark:bg-neutral-700 dark:text-neutral-300" x-text="item.hint"></span>
                        <span class="truncate" x-text="item.label"></span>
                    </button>
                </template>
            </div>
        </template>

        {{-- "Comment" bubble above the current text selection (mousedown, so the
             selection isn't cleared before the handler captures the anchor). --}}
        @if ($canWrite)
            <template x-teleport="body">
                <button type="button" x-show="commentBubble.show" x-cloak
                        @mousedown.prevent="commentSelection()"
                        :style="`top: ${commentBubble.y}px; left: ${commentBubble.x}px;`"
                        class="fixed z-[70] inline-flex items-center gap-1 rounded-lg bg-neutral-900 px-2.5 py-1 text-xs font-medium text-white shadow-lg dark:bg-neutral-700">
                    <x-phosphor-chat-teardrop-text class="h-3.5 w-3.5" /> {{ __('shelf::shelf.comment_add') }}
                </button>
            </template>
        @endif
    </div>

    {{-- Comments panel (Google-Docs-style anchored comments) --}}
    @if ($showComments)
        @include('shelf::partials.comments')
    @endif

    {{-- Revision history panel --}}
    @if ($showHistory)
        <aside class="flex w-80 shrink-0 flex-col border-l border-neutral-200 dark:border-neutral-800">
            <div class="flex items-center gap-2 border-b border-neutral-200 px-3 py-2.5 dark:border-neutral-800">
                <x-phosphor-clock-counter-clockwise class="h-4 w-4 text-neutral-400" />
                <span class="text-sm font-semibold">{{ __('shelf::shelf.history') }}</span>
                <button type="button" wire:click="toggleHistory" class="ml-auto rounded p-1 text-neutral-400 hover:bg-neutral-100 hover:text-neutral-600 dark:hover:bg-neutral-800"><x-phosphor-x class="h-4 w-4" /></button>
            </div>
            <div class="min-h-0 flex-1 space-y-1 overflow-y-auto p-2">
                @forelse ($revisions as $revision)
                    <button type="button" wire:click="viewRevision({{ $revision->id }})" wire:key="shelf-rev-{{ $revision->id }}"
                            class="w-full rounded-lg border px-2.5 py-2 text-left text-xs {{ $viewingRevisionId === $revision->id ? 'border-indigo-300 bg-indigo-50 dark:border-indigo-500/40 dark:bg-indigo-500/10' : 'border-neutral-200 hover:border-indigo-300 dark:border-neutral-800 dark:hover:border-indigo-500/40' }}">
                        <span class="block font-medium">{{ $revision->created_at->translatedFormat('d M Y H:i') }}</span>
                        <span class="mt-0.5 block text-neutral-500 dark:text-neutral-400">
                            {{ $revision->creator?->name ?? '—' }} · {{ $this->formatBytes($revision->size) }}
                        </span>
                    </button>
                @empty
                    <p class="px-2 py-6 text-center text-xs text-neutral-400 dark:text-neutral-500">{{ __('shelf::shelf.no_revisions') }}</p>
                @endforelse
            </div>
        </aside>
    @endif

    {{-- Revision diff overlay (the editor stays mounted underneath) --}}
    @if ($viewingRevision !== null)
        <div class="absolute inset-0 z-20 flex flex-col bg-white dark:bg-neutral-950 {{ $showHistory ? 'right-80' : '' }}">
            <div class="flex items-center gap-3 border-b border-neutral-200 px-4 py-2.5 dark:border-neutral-800">
                <x-phosphor-git-diff class="h-4 w-4 text-neutral-400" />
                <span class="text-sm font-semibold">{{ __('shelf::shelf.revision_of', ['date' => $viewingRevision->created_at->translatedFormat('d M Y H:i')]) }}</span>
                <span class="text-xs text-neutral-500 dark:text-neutral-400">{{ $viewingRevision->creator?->name ?? '—' }}</span>
                <div class="ml-auto flex items-center gap-2">
                    @if ($canWrite)
                        <button type="button" wire:click="restoreRevision({{ $viewingRevision->id }})"
                                wire:confirm="{{ __('shelf::shelf.restore_confirm') }}"
                                class="inline-flex items-center gap-1 rounded-lg bg-indigo-600 px-2.5 py-1 text-xs font-medium text-white hover:bg-indigo-500">
                            <x-phosphor-arrow-counter-clockwise class="h-3.5 w-3.5" /> {{ __('shelf::shelf.restore_this') }}
                        </button>
                    @endif
                    <button type="button" wire:click="closeRevision" class="rounded-lg border border-neutral-200 px-2.5 py-1 text-xs text-neutral-600 hover:bg-neutral-100 dark:border-neutral-700 dark:text-neutral-300 dark:hover:bg-neutral-800">{{ __('shelf::shelf.close') }}</button>
                </div>
            </div>
            <div class="min-h-0 flex-1 overflow-y-auto p-4 font-mono text-xs leading-5">
                <p class="mb-2 text-[11px] text-neutral-400 dark:text-neutral-500">{{ __('shelf::shelf.diff_legend') }}</p>
                @foreach ($revisionDiff as $line)
                    <div @class([
                        'whitespace-pre-wrap rounded-sm px-2',
                        'bg-red-50 text-red-700 dark:bg-red-500/10 dark:text-red-400' => $line['type'] === 'del',
                        'bg-green-50 text-green-700 dark:bg-green-500/10 dark:text-green-400' => $line['type'] === 'add',
                        'text-neutral-600 dark:text-neutral-300' => $line['type'] === 'same',
                    ])>{{ ($line['type'] === 'del' ? '- ' : ($line['type'] === 'add' ? '+ ' : '  ')).$line['text'] }}</div>
                @endforeach
            </div>
        </div>
    @endif
</div>

{{-- The note editor's Alpine component ships in dist/shelf.js, loaded once on
     the page by <x-plugin-assets> (see show.blade.php) — no inline script here. --}}
