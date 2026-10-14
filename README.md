# Laravel Chat

A reusable Laravel package for building a package-ready messaging layer that can be installed in multiple Laravel applications.

## Features

- Separate chat database connection
- Auth-aware user resolution via Laravel Auth or request ACL metadata
- Package configuration and migration publishing
- Local route, controller, model, middleware, and service scaffolding
- Extensible contracts and service classes for future package integration

## Local development

This checkout lives outside the host application at `C:\Users\paimisdev\laravel-chat`. The host application's Composer manifest uses a local path repository to install it as `vendor/laravel-chat` at local version `0.1.0`; package classes should not be added directly to the host application's PSR-4 mappings.

From the host application directory:

1. Run `composer update vendor/laravel-chat` to resolve the local package.
2. Publish config with `php artisan vendor:publish --tag=laravel-chat-config`.
3. Run `php artisan migrate --database=chat` when the chat database connection is configured.

The package is symlinked by Composer for local development, so source changes are immediately visible to the host application. When the package is moved or published, update the path repository in the host application's `composer.json` accordingly.

## Package conventions

The package intentionally avoids hardcoding host-specific business logic and keeps all chat tables in the dedicated `chat` database connection by default.
