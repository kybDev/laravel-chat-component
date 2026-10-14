<?php

use Illuminate\Support\Facades\Route;
use Vendor\LaravelChat\Http\Controllers\ChatController;
use Vendor\LaravelChat\Http\Controllers\ChatWorkspaceController;
use Vendor\LaravelChat\Http\Middleware\EnsureAuthenticatedChatUser;
use Vendor\LaravelChat\Http\Middleware\EnsureChatUser;

Route::middleware(config('chat.routes.middleware', ['web', 'auth', EnsureAuthenticatedChatUser::class]))
    ->prefix(config('chat.routes.prefix', 'messages'))
    ->group(function () {
        Route::get('/', [ChatController::class, 'index'])->name('chat.index');
        Route::get('/assets/chat.css', [ChatController::class, 'stylesheet'])->name('chat.assets.css');
        Route::get('/assets/chat.js', [ChatController::class, 'script'])->name('chat.assets.js');
        Route::get('/assets/workspace.js', [ChatController::class, 'workspaceScript'])->name('chat.assets.workspace.js');
        Route::get('/updates', [ChatController::class, 'updates'])->name('chat.updates');
        Route::get('/conversations', [ChatController::class, 'conversations'])->name('chat.conversations');
        Route::get('/conversations/{conversation}/messages', [ChatController::class, 'messages'])->name('chat.messages');
        Route::post('/messages', [ChatController::class, 'store'])->name('chat.store')->middleware(EnsureChatUser::class);

        Route::get('/users', [ChatWorkspaceController::class, 'users'])->name('chat.workspace.users');
        Route::post('/direct', [ChatWorkspaceController::class, 'createDirect'])->name('chat.workspace.direct');
        Route::post('/groups', [ChatWorkspaceController::class, 'createGroup'])->name('chat.workspace.groups');
        Route::get('/groups/{conversation}/members', [ChatWorkspaceController::class, 'groupMembers'])
            ->name('chat.workspace.groups.members.index');
        Route::post('/groups/{conversation}/members', [ChatWorkspaceController::class, 'addMembers'])
            ->name('chat.workspace.groups.members');
    });
