<?php

namespace Board\PluginShelf;

use Board\PluginSdk\Contracts\Plugin;
use Board\PluginSdk\Contracts\ProvidesBoardType;
use Board\PluginSdk\Contracts\ProvidesSettings;
use Board\PluginSdk\Support\PluginSettings;

/**
 * Shelf — the document shelf: a plugin-contributed BOARD TYPE. A Shelf board
 * replaces the kanban grid with a file-explorer tree of folders, markdown
 * notes and files, under a storage quota. The host handles membership, roles,
 * pinning and cross-workspace moves like any other board.
 */
class ShelfPlugin implements Plugin, ProvidesBoardType, ProvidesSettings
{
    /** Instance default when the admin has not configured a quota (in GB). */
    public const DEFAULT_QUOTA_GB = 5;

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
        return [[
            'key' => 'default_quota_gb',
            'label' => __('shelf::shelf.setting_default_quota'),
            'type' => 'number',
            'required' => false,
            'default' => self::DEFAULT_QUOTA_GB,
            'placeholder' => (string) self::DEFAULT_QUOTA_GB,
            'help' => __('shelf::shelf.setting_default_quota_help'),
        ]];
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
