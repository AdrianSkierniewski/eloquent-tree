<?php namespace Gzero\EloquentTree;

use Gzero\EloquentTree\Model\Observer;
use Gzero\EloquentTree\Model\Tree;
use Illuminate\Support\ServiceProvider;

class EloquentTreeServiceProvider extends ServiceProvider {

    /**
     * Indicates if loading of the provider is deferred.
     *
     * @var bool
     */
    protected $defer = FALSE;

    /**
     * Bootstrap the application events.
     *
     * @return void
     */
    public function boot()
    {
        $this->package('gzero/eloquent-tree');
    }

    /**
     * Register the service provider.
     *
     * @return void
     */
    public function register()
    {
        //
    }

    /**
     * Get the services provided by the provider.
     *
     * @return array
     */
    public function provides()
    {
        return array();
    }

}
