<?php

namespace Larvey\Option;

use Illuminate\Support\ServiceProvider as ServiceProviderBase;

class ServiceProvider extends ServiceProviderBase
{
    /**
     * Bootstrap the application services.
     *
     * @return void
     */
    public function boot()
    {
        $this->publishes([
            __DIR__.'/../config/option.php'                                  => config_path('option.php'),
            __DIR__.'/Migrations/2018_12_08_174545_create_options_table.php' => database_path('migrations/2018_12_08_174545_create_options_table.php'),
        ], 'option');

        $this->app->singleton('option', function ($app) {
            $connection = $app['db']->connection(config('setting.database.connection'));
            $table = config('option.database.table');

            return new OptionManager($connection, $table);
        });
    }

    /**
     * Register the application services.
     *
     * @return void
     */
    public function register()
    {
        $this->mergeConfigFrom(__DIR__.'/../config/option.php', 'option');
    }
}
