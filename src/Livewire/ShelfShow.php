<?php

namespace Board\PluginShelf\Livewire;

use App\Models\Board;
use Board\PluginShelf\Events\ShelfTreeUpdated;
use Board\PluginShelf\Models\ShelfBoard;
use Board\PluginShelf\Models\ShelfNode;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Layout;
use Livewire\Component;

/**
 * The full-page surface of a Shelf board: explorer tree (folders / notes /
 * files), soft trash and storage quota. Reads require the host's BoardPolicy
 * `view`; tree mutations require `contribute`; the quota override requires
 * `update` (board settings).
 */
#[Layout('components.layouts.app')]
class ShelfShow extends Component
{
    public Board $board;

    public bool $canWrite = false;

    public bool $canManage = false;

    public ?int $selectedNodeId = null;

    /** Inline creation state: the type being created and its target folder. */
    public ?string $creatingType = null;

    public ?int $creatingParentId = null;

    public string $newNodeName = '';

    public ?int $renamingNodeId = null;

    public string $renameValue = '';

    public bool $showTrash = false;

    /** Quota override form value in GB ('' = inherit the instance default). */
    public string $quotaInput = '';

    public function mount(Board $board): void
    {
        Gate::authorize('view', $board);

        abort_unless($board->type === 'shelf', 404);

        $this->board = $board;
        $this->canWrite = Gate::allows('contribute', $board);
        $this->canManage = Gate::allows('update', $board);

        $override = ShelfBoard::where('board_id', $board->id)->value('quota_gb');
        $this->quotaInput = $override !== null ? (string) $override : '';
    }

    /**
     * @return array<string, string>
     */
    public function getListeners(): array
    {
        return [
            "echo-private:board.{$this->board->id},.shelf.tree" => '$refresh',
        ];
    }

    // --- Selection & inline forms ----------------------------------------------

    public function selectNode(int $nodeId): void
    {
        $this->selectedNodeId = $this->node($nodeId)->id;
        $this->showTrash = false;
    }

    public function startCreating(string $type, ?int $parentId = null): void
    {
        abort_unless($this->canWrite, 403);

        if (! in_array($type, [ShelfNode::TYPE_FOLDER, ShelfNode::TYPE_NOTE], true)) {
            return;
        }

        $this->creatingType = $type;
        $this->creatingParentId = $parentId;
        $this->newNodeName = '';
        $this->renamingNodeId = null;
    }

    public function cancelCreating(): void
    {
        $this->creatingType = null;
        $this->creatingParentId = null;
        $this->newNodeName = '';
    }

    public function createNode(): void
    {
        abort_unless($this->canWrite, 403);

        if (! in_array($this->creatingType, [ShelfNode::TYPE_FOLDER, ShelfNode::TYPE_NOTE], true)) {
            return;
        }

        $this->validate(['newNodeName' => 'required|string|max:255'], [], ['newNodeName' => __('shelf::shelf.name')]);

        $parent = $this->creatingParentId !== null ? $this->folder($this->creatingParentId) : null;

        $node = ShelfNode::create([
            'board_id' => $this->board->id,
            'parent_id' => $parent?->id,
            'type' => $this->creatingType,
            'name' => trim($this->newNodeName),
            'position' => $this->nextPosition($parent?->id),
            'created_by' => Auth::id(),
        ]);

        $this->cancelCreating();
        $this->selectedNodeId = $node->id;
        $this->touchTree();
    }

    public function startRenaming(int $nodeId): void
    {
        abort_unless($this->canWrite, 403);

        $node = $this->node($nodeId);

        $this->renamingNodeId = $node->id;
        $this->renameValue = $node->name;
        $this->cancelCreating();
    }

    public function cancelRenaming(): void
    {
        $this->renamingNodeId = null;
        $this->renameValue = '';
    }

    public function renameNode(): void
    {
        abort_unless($this->canWrite, 403);

        if ($this->renamingNodeId === null) {
            return;
        }

        $this->validate(['renameValue' => 'required|string|max:255'], [], ['renameValue' => __('shelf::shelf.name')]);

        $this->node($this->renamingNodeId)->update(['name' => trim($this->renameValue)]);

        $this->cancelRenaming();
        $this->touchTree();
    }

    // --- Tree mutations ---------------------------------------------------------

    /**
     * Reparent a node (drag & drop). A null target moves it to the root. The
     * target must be a folder of the same board, and never the node itself nor
     * one of its descendants — that would detach the branch into a cycle.
     */
    public function moveNode(int $nodeId, ?int $targetFolderId): void
    {
        abort_unless($this->canWrite, 403);

        $node = $this->node($nodeId);
        $target = $targetFolderId !== null ? $this->folder($targetFolderId) : null;

        if ($target !== null) {
            for ($cursor = $target; $cursor !== null; $cursor = $cursor->parent) {
                if ($cursor->id === $node->id) {
                    return;
                }
            }
        }

        if (($target?->id ?? null) === $node->parent_id) {
            return;
        }

        $node->update([
            'parent_id' => $target?->id,
            'position' => $this->nextPosition($target?->id),
        ]);

        $this->touchTree();
    }

    public function trashNode(int $nodeId): void
    {
        abort_unless($this->canWrite, 403);

        $node = $this->node($nodeId);
        $node->update(['archived_at' => now()]);

        // The whole branch disappears from the tree; drop a selection that
        // pointed inside it.
        if ($this->selectedNodeId !== null && ! $this->nodeIsVisible($this->selectedNodeId)) {
            $this->selectedNodeId = null;
        }

        $this->cancelRenaming();
        $this->touchTree();
    }

    public function restoreNode(int $nodeId): void
    {
        abort_unless($this->canWrite, 403);

        $node = $this->node($nodeId);

        if (! $node->isTrashed()) {
            return;
        }

        // A parent that was itself trashed (or purged) can no longer host the
        // branch: restore to the root instead of resurrecting into limbo.
        $parent = $node->parent;
        $parentId = ($parent !== null && ! $parent->isTrashed()) ? $parent->id : null;

        $node->update([
            'archived_at' => null,
            'parent_id' => $parentId,
            'position' => $this->nextPosition($parentId),
        ]);

        $this->touchTree();
    }

    public function deleteForever(int $nodeId): void
    {
        abort_unless($this->canWrite, 403);

        $node = $this->node($nodeId);

        if (! $node->isTrashed()) {
            return;
        }

        $node->delete();

        $this->touchTree();
    }

    // --- Quota ------------------------------------------------------------------

    public function saveQuota(): void
    {
        abort_unless($this->canManage, 403);

        $this->validate(['quotaInput' => 'nullable|integer|min:1|max:10000'], [], ['quotaInput' => __('shelf::shelf.quota')]);

        $override = $this->quotaInput === '' ? null : (int) $this->quotaInput;

        ShelfBoard::updateOrCreate(['board_id' => $this->board->id], ['quota_gb' => $override]);
    }

    // --- Render -----------------------------------------------------------------

    public function render(): View
    {
        $nodes = ShelfNode::where('board_id', $this->board->id)
            ->orderBy('position')
            ->orderBy('name')
            ->get();

        $active = $nodes->filter(fn (ShelfNode $node): bool => ! $node->isTrashed());

        $selected = $this->selectedNodeId !== null ? $active->firstWhere('id', $this->selectedNodeId) : null;

        if ($selected === null) {
            $this->selectedNodeId = null;
        }

        $usedBytes = (int) $nodes->sum('size');
        $quotaBytes = ShelfBoard::quotaBytesFor($this->board);

        return view('shelf::show', [
            'childrenByParent' => $active->groupBy('parent_id'),
            'selectedNode' => $selected,
            'trashedNodes' => $nodes->filter(fn (ShelfNode $node): bool => $node->isTrashed())->sortByDesc('archived_at'),
            'usedBytes' => $usedBytes,
            'quotaBytes' => $quotaBytes,
            'usagePercent' => $quotaBytes > 0 ? min(100, (int) round($usedBytes * 100 / $quotaBytes)) : 0,
        ]);
    }

    /**
     * Human-readable size for the quota gauge (Go/Mo/Ko per the FR-source UI).
     */
    public function formatBytes(int $bytes): string
    {
        return match (true) {
            $bytes >= 1024 ** 3 => round($bytes / 1024 ** 3, $bytes >= 10 * 1024 ** 3 ? 0 : 1).' '.__('shelf::shelf.gb'),
            $bytes >= 1024 ** 2 => round($bytes / 1024 ** 2).' '.__('shelf::shelf.mb'),
            $bytes >= 1024 => round($bytes / 1024).' '.__('shelf::shelf.kb'),
            default => $bytes.' '.__('shelf::shelf.bytes'),
        };
    }

    // --- Internals --------------------------------------------------------------

    private function node(int $nodeId): ShelfNode
    {
        return ShelfNode::where('board_id', $this->board->id)->findOrFail($nodeId);
    }

    private function folder(int $nodeId): ShelfNode
    {
        $node = $this->node($nodeId);

        abort_unless($node->isFolder() && ! $node->isTrashed(), 422);

        return $node;
    }

    private function nextPosition(?int $parentId): int
    {
        return (int) ShelfNode::where('board_id', $this->board->id)
            ->where('parent_id', $parentId)
            ->max('position') + 1;
    }

    /**
     * Whether a node is still reachable from the roots (no trashed ancestor).
     */
    private function nodeIsVisible(int $nodeId): bool
    {
        $node = ShelfNode::where('board_id', $this->board->id)->find($nodeId);

        for ($cursor = $node; $cursor !== null; $cursor = $cursor->parent) {
            if ($cursor->isTrashed()) {
                return false;
            }
        }

        return $node !== null;
    }

    private function touchTree(): void
    {
        broadcast(new ShelfTreeUpdated($this->board->id))->toOthers();
    }
}
