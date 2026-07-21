<?php

namespace Board\PluginShelf;

use Board\PluginSdk\Contracts\Plugin;
use Board\PluginSdk\PluginServiceProvider;
use Board\PluginShelf\Livewire\ShelfShow;
use Board\PluginShelf\Models\ShelfNode;
use Illuminate\Console\Scheduling\Schedule;
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

        // The provider boots at runtime (plugin loader), after the host's
        // routes: the name lookup table must be refreshed or Route::has()
        // never sees 'shelf.show' and the board type is filtered out.
        $this->app['router']->getRoutes()->refreshNameLookups();

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
