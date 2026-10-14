<?php

use Illuminate\Support\Facades\Route;
use Vendor\LaravelChat\Http\Controllers\ChatController;
use Vendor\LaravelChat\Http\Middleware\EnsureChatUser;

Route::middleware(config('chat.routes.middleware', ['web', 'auth']))->prefix(config('chat.routes.prefix', 'messages'))->group(function () {
    Route::get('/', [ChatController::class, 'index'])->name('chat.index');
    Route::get('/assets/chat.css', [ChatController::class, 'stylesheet'])->name('chat.assets.css');
    Route::get('/assets/chat.js', [ChatController::class, 'script'])->name('chat.assets.js');
    Route::get('/updates', [ChatController::class, 'updates'])->name('chat.updates');
    Route::get('/conversations', [ChatController::class, 'conversations'])->name('chat.conversations');
    Route::get('/conversations/{conversation}/messages', [ChatController::class, 'messages'])->name('chat.messages');
    Route::post('/messages', [ChatController::class, 'store'])->name('chat.store')->middleware(EnsureChatUser::class);
});
