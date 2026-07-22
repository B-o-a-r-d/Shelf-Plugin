<?php

namespace Board\PluginShelf;

use App\Models\User;
use Board\PluginSdk\Contracts\Plugin;
use Board\PluginSdk\PluginServiceProvider;
use Board\PluginShelf\Http\ShelfExportController;
use Board\PluginShelf\Http\ShelfFileController;
use Board\PluginShelf\Http\ShelfPublicNoteController;
use Board\PluginShelf\Livewire\ShelfShow;
use Board\PluginShelf\Models\ShelfNode;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Support\Facades\Broadcast;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Route;

class ShelfServiceProvider extends PluginServiceProvider
{
    public function boot(): void
    {
        parent::boot();

        $this->loadViewsFrom(__DIR__.'/../resources/views', 'shelf');
        $this->loadMigrationsFrom(__DIR__.'/../database/migrations');

        // Runtime-registered routes MUST take their name through the fluent
        // registrar (name BEFORE get): on a production host with cached routes
        // the collection is compiled — ->name() after get() is never indexed
        // and refreshNameLookups() is a no-op there, so Route::has('shelf.show')
        // would stay false and the board type would be filtered out.
        Route::middleware(['web', 'auth', 'verified'])
            ->name('shelf.show')
            ->get('/shelf/{board:public_id}', ShelfShow::class);

        Route::middleware(['web', 'auth'])
            ->name('shelf.file')
            ->get('/shelf/file/{node:public_id}', ShelfFileController::class);

        Route::middleware(['web', 'auth'])
            ->name('shelf.export')
            ->get('/shelf/export/{node:public_id}', ShelfExportController::class);

        // Public, auth-free read-only view of a shared note, resolved by its
        // random share token. No board membership required — anyone with the
        // link can read the rendered markdown.
        Route::middleware(['web'])
            ->name('shelf.public')
            ->get('/shelf/public/{token}', ShelfPublicNoteController::class);

        // Presence channel of a note: who currently has it open. Authorized by
        // the host's BoardPolicy (view) on the node's board; the payload feeds
        // the "X édite cette note" indicator (avatars + typing whispers).
        Broadcast::channel('shelf-note.{nodeId}', function (User $user, string $nodeId) {
            $node = ShelfNode::find($nodeId);

            if ($node === null || ! Gate::forUser($user)->allows('view', $node->board)) {
                return false;
            }

            return [
                'id' => $user->id,
                'name' => $user->name,
                'avatar_url' => $user->avatarUrl(),
            ];
        });

        $this->callAfterResolving(Schedule::class, function (Schedule $schedule): void {
            $schedule->call(fn () => ShelfNode::purgeExpiredTrash())
                ->name('shelf-purge-trash')
                ->daily();
        });
    }

    protected function plugin(): Plugin
    {
        return new ShelfPlugin;
    }
}
