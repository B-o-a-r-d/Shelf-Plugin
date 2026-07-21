<?php

namespace Board\PluginShelf\Support;

use App\Models\Activity;
use App\Models\Board;
use Board\PluginShelf\Models\ShelfNode;
use Illuminate\Support\Facades\Auth;

/**
 * Journal of Shelf actions in the host's activity log (described back through
 * the plugin's DefinesActivities capability).
 */
final class ShelfActivity
{
    public static function log(Board $board, string $type, ShelfNode $node, array $extra = []): void
    {
        rescue(fn () => Activity::create([
            'board_id' => $board->id,
            'user_id' => Auth::id(),
            'type' => $type,
            'source' => 'plugin:shelf',
            'properties' => array_merge([
                'name' => $node->name,
                'node_type' => $node->type,
                'node_public_id' => $node->public_id,
            ], $extra),
        ]), report: false);
    }
}
