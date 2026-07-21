<?php

namespace Board\PluginShelf;

use Board\PluginSdk\Contracts\DefinesActivities;
use Board\PluginSdk\Contracts\Plugin;
use Board\PluginSdk\Contracts\ProvidesBoardType;
use Board\PluginSdk\Contracts\ProvidesMcpTools;
use Board\PluginSdk\Contracts\ProvidesSettings;
use Board\PluginSdk\Support\PluginSettings;
use Board\PluginShelf\Mcp\ShelfCreateNodeTool;
use Board\PluginShelf\Mcp\ShelfMoveNodeTool;
use Board\PluginShelf\Mcp\ShelfReadNoteTool;
use Board\PluginShelf\Mcp\ShelfTreeTool;
use Board\PluginShelf\Mcp\ShelfWriteNoteTool;

/**
 * Shelf — the document shelf: a plugin-contributed BOARD TYPE. A Shelf board
 * replaces the kanban grid with a file-explorer tree of folders, markdown
 * notes and files, under a storage quota. The host handles membership, roles,
 * pinning and cross-workspace moves like any other board.
 */
class ShelfPlugin implements DefinesActivities, Plugin, ProvidesBoardType, ProvidesMcpTools, ProvidesSettings
{
    /** Instance default when the admin has not configured a quota (in GB). */
    public const DEFAULT_QUOTA_GB = 5;

    /** Instance default number of note revisions kept per note. */
    public const DEFAULT_REVISIONS_KEEP = 20;

    public static function key(): string
    {
        return 'shelf';
    }

    public function label(): string
    {
        return 'Shelf';
    }

    public function description(): string
    {
        return __('shelf::shelf.plugin_description');
    }

    public function icon(): string
    {
        return 'books';
    }

    public function requiresOAuth(): bool
    {
        return false;
    }

    public function oauthProvider(): ?string
    {
        return null;
    }

    /**
     * Shelf has no per-board plugin instance config — a Shelf board is
     * configured through its own page (quota override, …).
     *
     * @return array<int, array<string, mixed>>
     */
    public function configFields(array $config = []): array
    {
        return [];
    }

    // --- ProvidesBoardType ------------------------------------------------------

    public function boardTypeKey(): string
    {
        return 'shelf';
    }

    public function boardTypeLabel(): string
    {
        return 'Shelf';
    }

    public function boardTypeIcon(): string
    {
        return 'books';
    }

    public function boardTypeRoute(): string
    {
        return 'shelf.show';
    }

    // --- ProvidesSettings (instance-level, marketplace admin UI) ----------------

    /**
     * @return array<int, array<string, mixed>>
     */
    public function settings(): array
    {
        return [
            [
                'key' => 'default_quota_gb',
                'label' => __('shelf::shelf.setting_default_quota'),
                'type' => 'number',
                'required' => false,
                'default' => self::DEFAULT_QUOTA_GB,
                'placeholder' => (string) self::DEFAULT_QUOTA_GB,
                'help' => __('shelf::shelf.setting_default_quota_help'),
            ],
            [
                'key' => 'revisions_keep',
                'label' => __('shelf::shelf.setting_revisions_keep'),
                'type' => 'number',
                'required' => false,
                'default' => self::DEFAULT_REVISIONS_KEEP,
                'placeholder' => (string) self::DEFAULT_REVISIONS_KEEP,
                'help' => __('shelf::shelf.setting_revisions_keep_help'),
            ],
        ];
    }

    // --- ProvidesMcpTools (the Trilium-like, drivable by an AI agent) -----------

    /**
     * @return array<int, class-string>
     */
    public function mcpTools(): array
    {
        return [
            ShelfTreeTool::class,
            ShelfReadNoteTool::class,
            ShelfWriteNoteTool::class,
            ShelfCreateNodeTool::class,
            ShelfMoveNodeTool::class,
        ];
    }

    // --- DefinesActivities -------------------------------------------------------

    /**
     * @return array{key: string, label: string}
     */
    public function activityTab(): array
    {
        return ['key' => 'shelf', 'label' => 'Shelf'];
    }

    /**
     * @return array<int, string>
     */
    public function activityTypes(): array
    {
        return [
            'shelf.node_created',
            'shelf.node_renamed',
            'shelf.node_moved',
            'shelf.node_trashed',
            'shelf.node_restored',
            'shelf.node_deleted',
            'shelf.note_edited',
            'shelf.file_uploaded',
        ];
    }

    public function describeActivity(string $type, array $properties): ?string
    {
        $name = (string) ($properties['name'] ?? '');

        return match ($type) {
            'shelf.node_created' => __('shelf::shelf.activity_created', ['name' => $name]),
            'shelf.node_renamed' => __('shelf::shelf.activity_renamed', ['from' => (string) ($properties['from'] ?? ''), 'name' => $name]),
            'shelf.node_moved' => __('shelf::shelf.activity_moved', ['name' => $name]),
            'shelf.node_trashed' => __('shelf::shelf.activity_trashed', ['name' => $name]),
            'shelf.node_restored' => __('shelf::shelf.activity_restored', ['name' => $name]),
            'shelf.node_deleted' => __('shelf::shelf.activity_deleted', ['name' => $name]),
            'shelf.note_edited' => __('shelf::shelf.activity_note_edited', ['name' => $name]),
            'shelf.file_uploaded' => __('shelf::shelf.activity_file_uploaded', ['name' => $name]),
            default => null,
        };
    }

    /**
     * How many revisions to keep per note (admin-configured, else built-in).
     */
    public static function revisionsKeep(): int
    {
        $configured = (int) PluginSettings::for(self::key())->get('revisions_keep', self::DEFAULT_REVISIONS_KEEP);

        return $configured > 0 ? $configured : self::DEFAULT_REVISIONS_KEEP;
    }

    /**
     * The instance-wide default quota in GB (admin-configured, else built-in).
     */
    public static function defaultQuotaGb(): int
    {
        $configured = (int) PluginSettings::for(self::key())->get('default_quota_gb', self::DEFAULT_QUOTA_GB);

        return $configured > 0 ? $configured : self::DEFAULT_QUOTA_GB;
    }
}
