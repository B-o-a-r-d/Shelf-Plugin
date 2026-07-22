<?php

namespace Board\PluginShelf\Livewire;

use App\Enums\BoardVisibility;
use App\Models\Board;
use Board\PluginSdk\Contracts\ProvidesBoardType;
use Board\PluginSdk\PluginRegistry;
use Board\PluginShelf\Events\ShelfTreeUpdated;
use Board\PluginShelf\Models\ShelfBoard;
use Board\PluginShelf\Models\ShelfNode;
use Board\PluginShelf\Models\ShelfNote;
use Board\PluginShelf\Models\ShelfNoteRevision;
use Board\PluginShelf\ShelfPlugin;
use Board\PluginShelf\Support\LineDiff;
use Board\PluginShelf\Support\QuotaExceededException;
use Board\PluginShelf\Support\ShelfActivity;
use Illuminate\Contracts\View\View;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Storage;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;
use Livewire\WithFileUploads;

/**
 * The full-page surface of a Shelf board: explorer tree (folders / notes /
 * files), soft trash and storage quota. Reads require the host's BoardPolicy
 * `view`; tree mutations require `contribute`; the quota override requires
 * `update` (board settings).
 */
#[Layout('components.layouts.app')]
class ShelfShow extends Component
{
    use WithFileUploads;

    /** Aligned on the instance-wide upload cap (200 MB), in kilobytes. */
    private const MAX_UPLOAD_KB = 204800;

    /**
     * Extensions importable as editable notes — text-based only, no external
     * converter: plugins must never require fiddling with the Docker image.
     */
    private const NOTE_EXTENSIONS = ['md', 'markdown', 'txt'];

    public Board $board;

    public bool $canWrite = false;

    public bool $canManage = false;

    public ?int $selectedNodeId = null;

    /** Tree search query ('' = the tree; 2+ chars = results list). */
    public string $search = '';

    /** Inline creation state: the type being created and its target folder. */
    public ?string $creatingType = null;

    public ?int $creatingParentId = null;

    public string $newNodeName = '';

    public ?int $renamingNodeId = null;

    public string $renameValue = '';

    public bool $showTrash = false;

    /** Quota override form value in GB ('' = inherit the instance default). */
    public string $quotaInput = '';

    /** Revision history panel of the selected note. */
    public bool $showHistory = false;

    public ?int $viewingRevisionId = null;

    /** @var array<int, TemporaryUploadedFile> */
    public array $uploads = [];

    public ?string $uploadError = null;

    /** Import modal: per-upload destiny ('file' | 'note' | 'tree'), by index. */
    public bool $showImportModal = false;

    /** @var array<int, string> */
    public array $importChoices = [];

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
        $this->showHistory = false;
        $this->viewingRevisionId = null;
        $this->search = '';
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

        ShelfActivity::log($this->board, 'shelf.node_created', $node);

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

        $node = $this->node($this->renamingNodeId);
        $from = $node->name;
        $node->update(['name' => trim($this->renameValue)]);

        ShelfActivity::log($this->board, 'shelf.node_renamed', $node, ['from' => $from]);

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

        ShelfActivity::log($this->board, 'shelf.node_moved', $node, ['to' => $target?->name]);

        $this->touchTree();
    }

    public function trashNode(int $nodeId): void
    {
        abort_unless($this->canWrite, 403);

        $node = $this->node($nodeId);
        $node->update(['archived_at' => now()]);

        ShelfActivity::log($this->board, 'shelf.node_trashed', $node);

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

        ShelfActivity::log($this->board, 'shelf.node_restored', $node);

        $this->touchTree();
    }

    public function deleteForever(int $nodeId): void
    {
        abort_unless($this->canWrite, 403);

        $node = $this->node($nodeId);

        if (! $node->isTrashed()) {
            return;
        }

        ShelfActivity::log($this->board, 'shelf.node_deleted', $node);

        // Model-by-model so every stored file of the branch is reclaimed.
        $node->deleteSubtree();

        $this->touchTree();
    }

    // --- File uploads -------------------------------------------------------------

    /**
     * Entry point of a dropped batch. Plain files are stored directly; as soon
     * as one upload could become a note (md/txt) or is a zip archive, the
     * import modal opens for a per-file choice — convert, unpack, or store
     * as-is.
     */
    public function saveUploads(): void
    {
        abort_unless($this->canWrite, 403);

        $this->uploadError = null;

        $this->validate(
            ['uploads.*' => 'file|max:'.self::MAX_UPLOAD_KB],
            [],
            ['uploads.*' => __('shelf::shelf.file')],
        );

        $choices = [];
        $needsModal = false;

        foreach ($this->uploads as $index => $upload) {
            $ext = strtolower(pathinfo($upload->getClientOriginalName(), PATHINFO_EXTENSION));

            if (in_array($ext, self::NOTE_EXTENSIONS, true)) {
                $choices[$index] = 'note';
                $needsModal = true;
            } elseif ($ext === 'zip') {
                $choices[$index] = 'tree';
                $needsModal = true;
            } else {
                $choices[$index] = 'file';
            }
        }

        if ($needsModal) {
            $this->importChoices = $choices;
            $this->showImportModal = true;

            return;
        }

        $this->processUploads($choices);
    }

    public function confirmImport(): void
    {
        abort_unless($this->canWrite, 403);

        $this->processUploads($this->importChoices);
    }

    public function cancelImport(): void
    {
        $this->uploads = [];
        $this->importChoices = [];
        $this->showImportModal = false;
    }

    /**
     * Persist the batch into the current folder (selected folder, or root),
     * upload by upload per its choice. The first item that would overflow the
     * quota stops the batch with a clear error; a failed conversion skips the
     * file but lets the rest land.
     *
     * @param  array<int, string>  $choices
     */
    private function processUploads(array $choices): void
    {
        $selected = $this->selectedNodeId !== null
            ? ShelfNode::where('board_id', $this->board->id)->find($this->selectedNodeId)
            : null;
        $parent = ($selected !== null && $selected->isFolder() && ! $selected->isTrashed()) ? $selected : null;

        $used = ShelfNode::usedBytes($this->board);
        $quota = ShelfBoard::quotaBytesFor($this->board);

        foreach ($this->uploads as $index => $upload) {
            try {
                match ($choices[$index] ?? 'file') {
                    'note' => $this->importAsNote($upload, $parent, $used, $quota),
                    'tree' => $this->importZip($upload, $parent, $used, $quota),
                    default => $this->storeUploadAsFile($upload, $parent, $used, $quota),
                };
            } catch (QuotaExceededException $e) {
                $this->uploadError = __('shelf::shelf.quota_upload_refused', ['name' => $e->itemName]);

                break;
            } catch (\RuntimeException $e) {
                report($e);
                $this->uploadError = __('shelf::shelf.import_failed', ['name' => $upload->getClientOriginalName()]);
            }
        }

        $this->uploads = [];
        $this->importChoices = [];
        $this->showImportModal = false;
        $this->touchTree();
    }

    private function storeUploadAsFile(UploadedFile $upload, ?ShelfNode $parent, int &$used, int $quota): void
    {
        $size = (int) $upload->getSize();

        if ($used + $size > $quota) {
            throw new QuotaExceededException($upload->getClientOriginalName());
        }

        $node = ShelfNode::create([
            'board_id' => $this->board->id,
            'parent_id' => $parent?->id,
            'type' => ShelfNode::TYPE_FILE,
            'name' => $upload->getClientOriginalName(),
            'position' => $this->nextPosition($parent?->id),
            'size' => $size,
            'mime' => $upload->getMimeType(),
            'created_by' => Auth::id(),
        ]);

        $safeName = preg_replace('/[^\w.\- ]+/u', '_', $upload->getClientOriginalName()) ?: 'fichier';
        $path = $upload->storeAs('shelf/'.$this->board->public_id.'/'.$node->public_id, $safeName, 'local');

        $node->update(['file_path' => $path]);

        ShelfActivity::log($this->board, 'shelf.file_uploaded', $node);

        $used += $size;
    }

    /**
     * Absorb a markdown / plain-text upload into an editable note node.
     */
    private function importAsNote(UploadedFile $upload, ?ShelfNode $parent, int &$used, int $quota): void
    {
        $original = $upload->getClientOriginalName();
        $markdown = self::readAsUtf8((string) file_get_contents((string) $upload->getRealPath()));
        $bytes = strlen($markdown);

        if ($used + $bytes > $quota) {
            throw new QuotaExceededException($original);
        }

        $node = ShelfNode::create([
            'board_id' => $this->board->id,
            'parent_id' => $parent?->id,
            'type' => ShelfNode::TYPE_NOTE,
            'name' => pathinfo($original, PATHINFO_FILENAME) ?: $original,
            'position' => $this->nextPosition($parent?->id),
            'size' => $bytes,
            'created_by' => Auth::id(),
        ]);

        ShelfNote::create(['node_id' => $node->id, 'markdown' => $markdown, 'version' => 1]);

        $used += $bytes;
        $this->selectedNodeId = $node->id;
    }

    /**
     * Text content with a latin-1 rescue for non-UTF-8 sources.
     */
    private static function readAsUtf8(string $content): string
    {
        return mb_check_encoding($content, 'UTF-8')
            ? $content
            : mb_convert_encoding($content, 'UTF-8', 'ISO-8859-1');
    }

    /**
     * Unpack a zip into a folder named after the archive, rebuilding its
     * directory tree; md/txt entries become notes, everything else files.
     * Traversal entries are skipped, oversized archives rejected.
     */
    private function importZip(UploadedFile $upload, ?ShelfNode $parent, int &$used, int $quota): void
    {
        $zip = new \ZipArchive;

        if ($zip->open((string) $upload->getRealPath()) !== true) {
            throw new \RuntimeException('Unreadable zip archive.');
        }

        try {
            if ($zip->numFiles > 2000) {
                throw new \RuntimeException('Zip archive has too many entries.');
            }

            $rootName = pathinfo($upload->getClientOriginalName(), PATHINFO_FILENAME) ?: 'archive';
            $root = ShelfNode::create([
                'board_id' => $this->board->id,
                'parent_id' => $parent?->id,
                'type' => ShelfNode::TYPE_FOLDER,
                'name' => $rootName,
                'position' => $this->nextPosition($parent?->id),
                'created_by' => Auth::id(),
            ]);

            /** @var array<string, ShelfNode> $folders */
            $folders = ['' => $root];

            $folderFor = function (string $dirPath) use (&$folders): ShelfNode {
                if (isset($folders[$dirPath])) {
                    return $folders[$dirPath];
                }

                $node = $folders[''];
                $walked = '';

                foreach (explode('/', $dirPath) as $segment) {
                    $walked = $walked === '' ? $segment : $walked.'/'.$segment;

                    $folders[$walked] ??= ShelfNode::create([
                        'board_id' => $this->board->id,
                        'parent_id' => $node->id,
                        'type' => ShelfNode::TYPE_FOLDER,
                        'name' => $segment,
                        'position' => $this->nextPosition($node->id),
                        'created_by' => Auth::id(),
                    ]);

                    $node = $folders[$walked];
                }

                return $node;
            };

            for ($i = 0; $i < $zip->numFiles; $i++) {
                $entry = (string) $zip->getNameIndex($i);

                // Traversal / absolute entries never touch the tree.
                if ($entry === '' || str_contains($entry, '..') || str_starts_with($entry, '/')) {
                    continue;
                }

                if (str_ends_with($entry, '/')) {
                    $folderFor(trim($entry, '/'));

                    continue;
                }

                $dir = str_contains($entry, '/') ? dirname($entry) : '';
                $folder = $folderFor($dir);
                $filename = basename($entry);
                $content = (string) $zip->getFromIndex($i);
                $bytes = strlen($content);

                if ($used + $bytes > $quota) {
                    throw new QuotaExceededException($filename);
                }

                if (in_array(strtolower(pathinfo($filename, PATHINFO_EXTENSION)), self::NOTE_EXTENSIONS, true)) {
                    $markdown = self::readAsUtf8($content);

                    $node = ShelfNode::create([
                        'board_id' => $this->board->id,
                        'parent_id' => $folder->id,
                        'type' => ShelfNode::TYPE_NOTE,
                        'name' => pathinfo($filename, PATHINFO_FILENAME) ?: $filename,
                        'position' => $this->nextPosition($folder->id),
                        'size' => strlen($markdown),
                        'created_by' => Auth::id(),
                    ]);

                    ShelfNote::create(['node_id' => $node->id, 'markdown' => $markdown, 'version' => 1]);
                } else {
                    $node = ShelfNode::create([
                        'board_id' => $this->board->id,
                        'parent_id' => $folder->id,
                        'type' => ShelfNode::TYPE_FILE,
                        'name' => $filename,
                        'position' => $this->nextPosition($folder->id),
                        'size' => $bytes,
                        'mime' => finfo_buffer(finfo_open(FILEINFO_MIME_TYPE), $content) ?: 'application/octet-stream',
                        'created_by' => Auth::id(),
                    ]);

                    $safeName = preg_replace('/[^\w.\- ]+/u', '_', $filename) ?: 'fichier';
                    $path = 'shelf/'.$this->board->public_id.'/'.$node->public_id.'/'.$safeName;

                    Storage::disk('local')->put($path, $content);
                    $node->update(['file_path' => $path]);
                }

                $used += $bytes;
            }

            $this->selectedNodeId = $root->id;
        } finally {
            $zip->close();
        }
    }

    // --- Notes (markdown, optimistic versioning) ---------------------------------

    /**
     * Autosave endpoint of the note editor. Optimistic concurrency: the client
     * sends the version it loaded; a mismatch means someone saved meanwhile and
     * the caller gets the server state back instead of overwriting it.
     *
     * @return array<string, mixed>
     */
    public function saveNote(int $nodeId, string $markdown, int $baseVersion): array
    {
        abort_unless($this->canWrite, 403);

        $node = $this->node($nodeId);

        abort_unless($node->type === ShelfNode::TYPE_NOTE && ! $node->isTrashed(), 422);

        $note = ShelfNote::firstOrNew(['node_id' => $node->id]);
        $currentVersion = $note->exists ? $note->version : 0;

        if ($baseVersion !== $currentVersion) {
            return [
                'ok' => false,
                'reason' => 'conflict',
                'version' => $currentVersion,
                'markdown' => (string) $note->markdown,
            ];
        }

        // Quota: refuse the delta that would tip the board over its cap.
        $delta = strlen($markdown) - strlen((string) $note->markdown);

        if ($delta > 0 && ShelfNode::usedBytes($this->board) + $delta > ShelfBoard::quotaBytesFor($this->board)) {
            return ['ok' => false, 'reason' => 'quota'];
        }

        // Log at snapshot cadence (~10 min), not on every debounced autosave.
        if ($note->persistContent($node, $markdown, Auth::id(), ShelfPlugin::revisionsKeep())) {
            ShelfActivity::log($this->board, 'shelf.note_edited', $node);
        }

        return ['ok' => true, 'version' => $note->version];
    }

    /**
     * Server state of a note, for conflict resolution ("Recharger").
     *
     * @return array<string, mixed>
     */
    public function reloadNote(int $nodeId): array
    {
        $node = $this->node($nodeId);
        $note = ShelfNote::firstWhere('node_id', $node->id);

        return [
            'markdown' => (string) $note?->markdown,
            'version' => $note?->version ?? 0,
        ];
    }

    // --- Revisions ---------------------------------------------------------------

    public function toggleHistory(): void
    {
        $this->showHistory = ! $this->showHistory;
        $this->viewingRevisionId = null;
    }

    public function viewRevision(int $revisionId): void
    {
        $this->viewingRevisionId = $this->revision($revisionId)->id;
    }

    public function closeRevision(): void
    {
        $this->viewingRevisionId = null;
    }

    /**
     * Bring a revision's content back as the current version. The pre-restore
     * content is snapshotted first, so a restore never loses anything.
     */
    public function restoreRevision(int $revisionId): void
    {
        abort_unless($this->canWrite, 403);

        $revision = $this->revision($revisionId);
        $note = $revision->note;

        $note->snapshot(Auth::id(), ShelfPlugin::revisionsKeep());

        $note->markdown = (string) $revision->markdown;
        $note->version = $note->version + 1;
        $note->save();

        $note->node->update(['size' => $note->weightBytes()]);

        $this->viewingRevisionId = null;
        $this->showHistory = false;

        // The editor is wire:ignore — hand it the restored content directly.
        $this->dispatch('shelf-note-restored',
            nodeId: $note->node_id,
            markdown: (string) $note->markdown,
            version: $note->version,
        );
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

        // Reachable = active AND on a fully-active path from the roots (a
        // trashed folder hides its whole branch from tree and search alike).
        $childrenMap = $active->groupBy('parent_id');
        $reachable = collect();
        $stack = ($childrenMap->get(null) ?? collect())->all();

        while ($stack !== []) {
            $node = array_pop($stack);
            $reachable->push($node);

            foreach ($childrenMap->get($node->id, collect()) as $child) {
                $stack[] = $child;
            }
        }

        $selected = $this->selectedNodeId !== null ? $active->firstWhere('id', $this->selectedNodeId) : null;

        if ($selected === null) {
            $this->selectedNodeId = null;
        }

        $usedBytes = (int) $nodes->sum('size');
        $quotaBytes = ShelfBoard::quotaBytesFor($this->board);

        // Note payload for the (wire:ignore) editor + revision panel data.
        $note = null;
        $revisions = collect();
        $viewingRevision = null;
        $revisionDiff = [];

        if ($selected?->type === ShelfNode::TYPE_NOTE) {
            $note = ShelfNote::firstWhere('node_id', $selected->id);

            if ($this->showHistory) {
                $revisions = $note?->revisions()->with('creator')->get() ?? collect();
            }

            if ($this->viewingRevisionId !== null) {
                $viewingRevision = $revisions->firstWhere('id', $this->viewingRevisionId);

                if ($viewingRevision === null) {
                    $this->viewingRevisionId = null;
                } else {
                    $revisionDiff = LineDiff::compute((string) $viewingRevision->markdown, (string) $note?->markdown);
                }
            }
        }

        return view('shelf::show', [
            'childrenByParent' => $childrenMap,
            'searchResults' => $this->searchResults($reachable),
            'selectedNode' => $selected,
            'trashedNodes' => $nodes->filter(fn (ShelfNode $node): bool => $node->isTrashed())->sortByDesc('archived_at'),
            'usedBytes' => $usedBytes,
            'quotaBytes' => $quotaBytes,
            'usagePercent' => $quotaBytes > 0 ? min(100, (int) round($usedBytes * 100 / $quotaBytes)) : 0,
            'note' => $note,
            'revisions' => $revisions,
            'viewingRevision' => $viewingRevision,
            'revisionDiff' => $revisionDiff,
            // Board navbar (same anatomy as the kanban header): every board of
            // the workspace this user can open, each routed to its surface.
            'switcherBoards' => $this->board->workspace->boards()
                ->notArchived()
                ->where('is_template', false)
                ->where(function ($scoped) {
                    $scoped->where('visibility', BoardVisibility::Workspace)
                        ->orWhereHas('members', fn ($members) => $members->whereKey(Auth::id()));
                })
                ->orderBy('position')
                ->get(['id', 'name', 'public_id', 'workspace_id', 'type']),
            'switcherTypes' => $this->boardTypes(),
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

    /**
     * Search over the reachable tree: node names in memory, note contents via
     * Postgres full-text (tsvector + GIN, prefix-matched and ranked) with a
     * portable LIKE fallback on other drivers. Each content hit carries a
     * ±60-char snippet around the first matching term.
     *
     * @param  Collection<int, ShelfNode>  $reachable
     * @return array<int, array{node: ShelfNode, snippet: string|null}>
     */
    private function searchResults($reachable): array
    {
        $query = trim($this->search);

        if (mb_strlen($query) < 2) {
            return [];
        }

        $results = [];

        // Names: lenient in-memory substring (covers folders, files and notes;
        // the tree is already loaded).
        foreach ($reachable as $node) {
            if (mb_stripos($node->name, $query) !== false) {
                $results[$node->id] = ['node' => $node, 'snippet' => null];
            }
        }

        $noteIds = $reachable
            ->filter(fn (ShelfNode $node): bool => $node->type === ShelfNode::TYPE_NOTE)
            ->pluck('id');

        foreach ($this->searchNoteContents($noteIds, $query) as $match) {
            $node = $reachable->firstWhere('id', $match->node_id);

            if ($node === null) {
                continue;
            }

            $results[$node->id] = [
                'node' => $node,
                'snippet' => $results[$node->id]['snippet'] ?? $this->snippet((string) $match->markdown, $query),
            ];
        }

        return array_slice(array_values($results), 0, 30);
    }

    /**
     * Notes whose content matches the query, ranked. Postgres full-text with
     * prefix matching where available; a portable case-insensitive LIKE
     * otherwise (sqlite in tests).
     *
     * @param  Collection<int, int>  $noteIds
     * @return Collection<int, ShelfNote>
     */
    private function searchNoteContents($noteIds, string $query): Collection
    {
        if ($noteIds->isEmpty()) {
            return collect();
        }

        $base = ShelfNote::whereIn('node_id', $noteIds);

        if (DB::connection()->getDriverName() === 'pgsql') {
            $tsquery = $this->toPrefixTsQuery($query);

            if ($tsquery === '') {
                return collect();
            }

            return $base
                ->whereRaw("search_vector @@ to_tsquery('simple', ?)", [$tsquery])
                ->orderByRaw("ts_rank(search_vector, to_tsquery('simple', ?)) desc", [$tsquery])
                ->limit(30)
                ->get(['node_id', 'markdown']);
        }

        return $base
            ->whereRaw('LOWER(markdown) LIKE ?', ['%'.mb_strtolower($query).'%'])
            ->get(['node_id', 'markdown']);
    }

    /**
     * Turn free text into a safe prefix tsquery — e.g. "pomm gold" becomes
     * "pomm:* & gold:*". Only word/number tokens reach to_tsquery, so no
     * user input can break its syntax.
     */
    private function toPrefixTsQuery(string $query): string
    {
        preg_match_all('/[\p{L}\p{N}]+/u', mb_strtolower($query), $matches);

        $terms = array_slice($matches[0], 0, 6);

        return implode(' & ', array_map(fn (string $term): string => $term.':*', $terms));
    }

    /**
     * A ±60-char plain-text snippet around the first matching term in the note.
     */
    private function snippet(string $markdown, string $query): string
    {
        $position = mb_stripos($markdown, $query);

        if ($position === false) {
            // The tsquery matched a prefix — locate the first query word.
            preg_match('/[\p{L}\p{N}]+/u', $query, $word);
            $position = $word !== [] ? mb_stripos($markdown, $word[0]) : false;
        }

        $position = $position === false ? 0 : $position;
        $start = max(0, $position - 60);

        return ($start > 0 ? '…' : '')
            .trim(mb_substr($markdown, $start, 120 + mb_strlen($query)))
            .'…';
    }

    /**
     * Board types contributed by active plugins, resolved through the SDK
     * registry (the plugin never touches host internals): the switcher routes
     * typed boards to their plugin page and skips orphan types.
     *
     * @return array<string, array{key: string, label: string, icon: string, route: string}>
     */
    private function boardTypes(): array
    {
        $types = [];

        foreach (app(PluginRegistry::class)->all() as $plugin) {
            if (! $plugin instanceof ProvidesBoardType) {
                continue;
            }

            $key = $plugin->boardTypeKey();

            if ($key === Board::TYPE_KANBAN || ! Route::has($plugin->boardTypeRoute())) {
                continue;
            }

            $types[$key] = [
                'key' => $key,
                'label' => $plugin->boardTypeLabel(),
                'icon' => $plugin->boardTypeIcon(),
                'route' => $plugin->boardTypeRoute(),
            ];
        }

        return $types;
    }

    // --- Internals --------------------------------------------------------------

    private function node(int $nodeId): ShelfNode
    {
        return ShelfNode::where('board_id', $this->board->id)->findOrFail($nodeId);
    }

    private function revision(int $revisionId): ShelfNoteRevision
    {
        $revision = ShelfNoteRevision::with('note.node')->findOrFail($revisionId);

        abort_unless($revision->note?->node?->board_id === $this->board->id, 404);

        return $revision;
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
