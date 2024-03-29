<?php

namespace CanhKieu\LaravelWebSockets;

use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;
use BeyondCode\LaravelWebSockets\Server\Router;
use BeyondCode\LaravelWebSockets\Apps\AppProvider;
use BeyondCode\LaravelWebSockets\WebSockets\Channels\ChannelManager;
use BeyondCode\LaravelWebSockets\Dashboard\Http\Controllers\SendMessage;
use BeyondCode\LaravelWebSockets\Dashboard\Http\Controllers\ShowDashboard;
use BeyondCode\LaravelWebSockets\Dashboard\Http\Controllers\AuthenticateDashboard;
use BeyondCode\LaravelWebSockets\Dashboard\Http\Controllers\DashboardApiController;
use BeyondCode\LaravelWebSockets\WebSockets\Channels\ChannelManagers\ArrayChannelManager;
use BeyondCode\LaravelWebSockets\Dashboard\Http\Middleware\Authorize as AuthorizeDashboard;
use BeyondCode\LaravelWebSockets\Statistics\Http\Middleware\Authorize as AuthorizeStatistics;
use BeyondCode\LaravelWebSockets\Statistics\Http\Controllers\WebSocketStatisticsEntriesController;

use CanhKieu\LaravelWebSockets\Console\Commands\StartWebSocketServer;
use BeyondCode\LaravelWebSockets\Console\CleanStatistics;

class WebSocketsServiceProvider extends ServiceProvider
{

    public function boot()
    {

        $this->publishes([
            base_path('vendor/beyondcode/laravel-websockets/src') . '/../config/websockets.php' => base_path('config/websockets.php'),
        ], 'config');

        if (!class_exists('CreateWebSocketsStatisticsEntries')) {
            $this->publishes([
                base_path('vendor/beyondcode/laravel-websockets/src') . '/../database/migrations/create_websockets_statistics_entries_table.php.stub' => database_path('migrations/' . date('Y_m_d_His', time()) . '_create_websockets_statistics_entries_table.php'),
            ], 'migrations');
        }

        $this
            ->registerRoutes()
            ->registerDashboardGate();

        $this->loadViewsFrom(base_path('vendor/beyondcode/laravel-websockets/src') . '/../resources/views/', 'websockets');

        $this->commands([
            StartWebSocketServer::class,
            CleanStatistics::class,
        ]);
    }

    public function register()
    {
        $this->mergeConfigFrom(base_path('vendor/beyondcode/laravel-websockets/src') . '/../config/websockets.php', 'websockets');

        $this->app->singleton('websockets.router', function () {
            return new Router();
        });

        $this->app->singleton(ChannelManager::class, function () {
            return config('websockets.channel_manager') !== null && class_exists(config('websockets.channel_manager'))
                ? app(config('websockets.channel_manager')) : new ArrayChannelManager();
        });

        $this->app->singleton(AppProvider::class, function () {
            return app(config('websockets.app_provider'));
        });
    }

    protected function registerRoutes()
    {
        Route::group(['prefix' => config('websockets.path'), 'as' => 'websockets'], function () {
            Route::group(['middleware' => config('websockets.middleware', [AuthorizeDashboard::class])], function () {
                Route::get('/', ShowDashboard::class);
                Route::get('/api/{appId}/statistics', function () {
                    return "OK";
                });
                Route::post('auth', AuthenticateDashboard::class);
                Route::post('event', SendMessage::class);
            });

            Route::group(['middleware' => AuthorizeStatistics::class], function () {
                Route::post('statistics', [
                    'as' => 'statistics',
                    'uses' =>
                    WebSocketStatisticsEntriesController::class, 'store'
                ]);
            });
        });
        return $this;
    }

    protected function registerDashboardGate()
    {
        Gate::define('viewWebSocketsDashboard', function ($user = null) {
            return app()->environment('local');
        });

        return $this;
    }
}
