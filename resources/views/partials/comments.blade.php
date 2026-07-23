{{-- Comments panel: anchored threads with replies + resolve, Google-Docs-style.
     Server-rendered (outside the wire:ignore editor) so it updates live on any
     comment change (local action or remote broadcast). --}}
<aside class="flex w-80 shrink-0 flex-col border-l border-neutral-200 dark:border-neutral-800">
    <div class="flex items-center gap-2 border-b border-neutral-200 px-3 py-2.5 dark:border-neutral-800">
        <x-phosphor-chat-circle-text class="h-4 w-4 text-neutral-400" />
        <span class="text-sm font-semibold">{{ __('shelf::shelf.comments') }}</span>
        <button type="button" wire:click="toggleComments" class="ml-auto rounded p-1 text-neutral-400 hover:bg-neutral-100 hover:text-neutral-600 dark:hover:bg-neutral-800"><x-phosphor-x class="h-4 w-4" /></button>
    </div>

    <div class="min-h-0 flex-1 space-y-2 overflow-y-auto p-2">
        {{-- Compose box for the pending selection anchor --}}
        @if ($canWrite && $pendingQuote !== null)
            <div class="rounded-lg border border-indigo-200 bg-indigo-50/60 p-2 dark:border-indigo-500/30 dark:bg-indigo-500/5">
                <p class="mb-1.5 line-clamp-2 border-l-2 border-amber-400 pl-2 text-xs italic text-neutral-500 dark:text-neutral-400">« {{ \Illuminate\Support\Str::limit($pendingQuote, 140) }} »</p>
                <textarea wire:model="commentDraft" rows="2" autofocus
                          placeholder="{{ __('shelf::shelf.comment_placeholder') }}"
                          class="w-full rounded-lg border border-neutral-200 bg-white px-2 py-1 text-xs focus:border-indigo-500 focus:outline-none dark:border-neutral-700 dark:bg-neutral-900"></textarea>
                @error('commentDraft') <p class="mt-0.5 text-[11px] text-red-600 dark:text-red-400">{{ $message }}</p> @enderror
                <div class="mt-1.5 flex justify-end gap-1.5">
                    <button type="button" wire:click="cancelComment" class="rounded-lg px-2 py-1 text-[11px] text-neutral-500 hover:bg-neutral-100 dark:hover:bg-neutral-800">{{ __('shelf::shelf.comment_cancel') }}</button>
                    <button type="button" wire:click="submitComment" class="rounded-lg bg-indigo-600 px-2.5 py-1 text-[11px] font-medium text-white hover:bg-indigo-500">{{ __('shelf::shelf.comment_submit') }}</button>
                </div>
            </div>
        @endif

        @forelse ($comments as $thread)
            <div wire:key="shelf-comment-{{ $thread->id }}"
                 @class([
                     'rounded-lg border p-2 transition',
                     'border-indigo-300 ring-1 ring-indigo-200 dark:border-indigo-500/40 dark:ring-indigo-500/20' => $focusedCommentId === $thread->id,
                     'border-neutral-200 dark:border-neutral-800' => $focusedCommentId !== $thread->id,
                     'opacity-70' => $thread->isResolved(),
                 ])>
                @if ($thread->anchor_quote)
                    <button type="button" wire:click="focusComment({{ $thread->id }})"
                            class="mb-1.5 block w-full truncate border-l-2 border-amber-400 pl-2 text-left text-[11px] italic text-neutral-500 hover:text-neutral-700 dark:text-neutral-400 dark:hover:text-neutral-200">
                        « {{ \Illuminate\Support\Str::limit($thread->anchor_quote, 90) }} »
                    </button>
                @endif

                <div class="flex items-start justify-between gap-2">
                    <div class="min-w-0">
                        <span class="text-xs font-semibold text-neutral-800 dark:text-neutral-100">{{ $thread->author?->name ?? '—' }}</span>
                        <span class="ml-1 text-[10px] text-neutral-400">{{ $thread->created_at->diffForHumans() }}</span>
                    </div>
                    @if ($canWrite)
                        <div class="flex shrink-0 items-center gap-0.5">
                            @if ($thread->isResolved())
                                <button type="button" wire:click="reopenComment({{ $thread->id }})" title="{{ __('shelf::shelf.comment_reopen') }}" class="rounded p-1 text-neutral-400 hover:bg-neutral-100 hover:text-neutral-600 dark:hover:bg-neutral-800"><x-phosphor-arrow-counter-clockwise class="h-3.5 w-3.5" /></button>
                            @else
                                <button type="button" wire:click="resolveComment({{ $thread->id }})" title="{{ __('shelf::shelf.comment_resolve') }}" class="rounded p-1 text-neutral-400 hover:bg-emerald-50 hover:text-emerald-600 dark:hover:bg-emerald-500/10"><x-phosphor-check-circle class="h-3.5 w-3.5" /></button>
                            @endif
                            @if ($canManage || $thread->created_by === auth()->id())
                                <button type="button" wire:click="deleteComment({{ $thread->id }})" wire:confirm="{{ __('shelf::shelf.delete_forever_confirm', ['name' => \Illuminate\Support\Str::limit((string) $thread->body, 30)]) }}" title="{{ __('shelf::shelf.comment_delete') }}" class="rounded p-1 text-neutral-400 hover:bg-red-50 hover:text-red-600 dark:hover:bg-red-500/10"><x-phosphor-trash class="h-3.5 w-3.5" /></button>
                            @endif
                        </div>
                    @endif
                </div>

                <p class="mt-0.5 whitespace-pre-wrap break-words text-xs text-neutral-700 dark:text-neutral-300">{{ $thread->body }}</p>

                @if ($thread->isResolved())
                    <p class="mt-1 inline-flex items-center gap-1 text-[10px] font-medium text-emerald-600 dark:text-emerald-400"><x-phosphor-check-circle class="h-3 w-3" /> {{ __('shelf::shelf.comment_resolved_by', ['name' => $thread->resolver?->name ?? '—']) }}</p>
                @endif

                @foreach ($thread->replies as $reply)
                    <div class="mt-1.5 border-l border-neutral-200 pl-2 dark:border-neutral-700" wire:key="shelf-reply-{{ $reply->id }}">
                        <span class="text-xs font-semibold text-neutral-800 dark:text-neutral-100">{{ $reply->author?->name ?? '—' }}</span>
                        <span class="ml-1 text-[10px] text-neutral-400">{{ $reply->created_at->diffForHumans() }}</span>
                        <p class="whitespace-pre-wrap break-words text-xs text-neutral-700 dark:text-neutral-300">{{ $reply->body }}</p>
                    </div>
                @endforeach

                @if ($canWrite && ! $thread->isResolved())
                    <div class="mt-1.5 flex items-center gap-1">
                        <input type="text" wire:model="replyDrafts.{{ $thread->id }}" wire:keydown.enter="replyToComment({{ $thread->id }})"
                               placeholder="{{ __('shelf::shelf.comment_reply_placeholder') }}"
                               class="min-w-0 flex-1 rounded-lg border border-neutral-200 bg-white px-2 py-1 text-[11px] focus:border-indigo-500 focus:outline-none dark:border-neutral-700 dark:bg-neutral-900">
                        <button type="button" wire:click="replyToComment({{ $thread->id }})" class="rounded-lg border border-neutral-200 px-2 py-1 text-[11px] text-neutral-600 hover:bg-neutral-100 dark:border-neutral-700 dark:text-neutral-300 dark:hover:bg-neutral-800">{{ __('shelf::shelf.comment_reply') }}</button>
                    </div>
                @endif
            </div>
        @empty
            <p class="px-2 py-6 text-center text-xs text-neutral-400 dark:text-neutral-500">{{ __('shelf::shelf.comment_empty') }}</p>
        @endforelse
    </div>
</aside>
