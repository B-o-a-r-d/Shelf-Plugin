<?php

namespace Board\PluginShelf;

use App\Models\User;
use Board\PluginSdk\Contracts\Plugin;
use Board\PluginSdk\PluginServiceProvider;
use Board\PluginShelf\Http\ShelfFileController;
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

        Route::middleware(['web', 'auth', 'verified'])
            ->get('/shelf/{board:public_id}', ShelfShow::class)
            ->name('shelf.show');

        Route::middleware(['web', 'auth'])
            ->get('/shelf/file/{node:public_id}', ShelfFileController::class)
            ->name('shelf.file');

        // The provider boots at runtime (plugin loader), after the host's
        // routes: the name lookup table must be refreshed or Route::has()
        // never sees 'shelf.show' and the board type is filtered out.
        $this->app['router']->getRoutes()->refreshNameLookups();

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
