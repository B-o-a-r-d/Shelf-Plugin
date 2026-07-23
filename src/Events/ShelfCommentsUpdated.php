<?php

namespace Board\PluginShelf\Events;

use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * "Comments on a note changed" signal on the board's private channel (same
 * BoardPolicy@view authorization as the tree). Other viewers re-render so a new
 * comment / reply / resolution appears live.
 */
class ShelfCommentsUpdated implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(public int $boardId, public int $noteId) {}

    public function broadcastOn(): PrivateChannel
    {
        return new PrivateChannel('board.'.$this->boardId);
    }

    public function broadcastAs(): string
    {
        return 'shelf.comments';
    }
}
