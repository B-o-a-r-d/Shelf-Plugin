<?php

namespace Board\PluginShelf\Events;

use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Coarse-grained "the tree changed" signal on the board's existing private
 * channel (authorized by the host's BoardPolicy@view). Other viewers of the
 * Shelf page re-render on receipt; the sender is excluded (toOthers).
 */
class ShelfTreeUpdated implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(public int $boardId) {}

    public function broadcastOn(): PrivateChannel
    {
        return new PrivateChannel('board.'.$this->boardId);
    }

    public function broadcastAs(): string
    {
        return 'shelf.tree';
    }
}
