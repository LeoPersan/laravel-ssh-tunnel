<?php namespace	LeoPersan\Tunneler;

use Illuminate\Support\ServiceProvider;
use LeoPersan\Tunneler\Console\TunnelerCommand;
use LeoPersan\Tunneler\Console\TunnelerReset;
use LeoPersan\Tunneler\Jobs\CreateTunnel;


class TunnelerServiceProvider extends ServiceProvider{
    /**
     * Indicates if loading of the provider is deferred.
     *
     * @var bool
     */
    protected $defer = false;

    /**
     * Default path to configuration
     * @var string
     */
    protected $configPath = __DIR__ . '/../config/tunneler.php';


    public function boot(): void
    {
        // helps deal with Lumen vs Laravel differences
        if (function_exists('config_path')) {
            $publishPath = config_path('tunneler.php');
        } else {
            $publishPath = base_path('config/tunneler.php');
        }

        $this->publishes([$this->configPath => $publishPath], 'config');

        foreach (config('tunneler.connections') as $connection => $config) {
            $config = array_merge(config('tunneler.default'), $config);
            config(['tunneler.connections.' . $connection => $config]);
            if ($config['on_boot']) {
                dispatch(new CreateTunnel($connection));
            }
        }

        if ($this->app->runningInConsole()) {
            $this->commands([
                TunnelerCommand::class,
                TunnelerReset::class,
            ]);
        }
    }

    public function register(): void
    {
        if ( is_a($this->app,'Laravel\Lumen\Application')){
            $this->app->configure('tunneler');
        }
        $this->mergeConfigFrom($this->configPath, 'tunneler');

        $this->app->singleton('command.tunneler.activate',
            function ($app) {
                return new TunnelerCommand();
            }
        );

        $this->commands('command.tunneler.activate');

        $this->app->singleton('command.tunneler.reset',
            function ($app) {
                return new TunnelerReset();
            }
        );

        $this->commands('command.tunneler.reset');
    }

    /**
     * Get the services provided by the provider.
     *
     * @return array
     */
    public function provides(): array
    {
        return ['command.tunneler.activate'];
    }

}
