<?php

namespace Vendor\LaravelChat;

use Illuminate\Support\ServiceProvider;

class LaravelChatServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__ . '/../config/chat.php', 'chat');
    }

    public function boot(): void
    {
        $this->loadRoutesFrom(__DIR__ . '/../routes/web.php');
        $this->loadRoutesFrom(__DIR__ . '/../routes/api.php');
        $this->loadMigrationsFrom(__DIR__ . '/../database/migrations');
        $this->loadViewsFrom(__DIR__ . '/../resources/views', 'laravel-chat');

        $this->publishes([
            __DIR__ . '/../config/chat.php' => config_path('chat.php'),
        ], 'laravel-chat-config');

        $this->publishes([
            __DIR__ . '/../resources/js' => resource_path('js/vendor/laravel-chat'),
            __DIR__ . '/../resources/css' => resource_path('css/vendor/laravel-chat'),
        ], 'laravel-chat-assets');

        $this->publishes([
            __DIR__ . '/../resources/views' => resource_path('views/vendor/laravel-chat'),
        ], 'laravel-chat-views');
    }
}
