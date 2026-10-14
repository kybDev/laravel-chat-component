<?php

use Illuminate\Support\Facades\Route;
use Vendor\LaravelChat\Http\Controllers\ChatController;
use Vendor\LaravelChat\Http\Middleware\EnsureChatUser;

Route::middleware(config('chat.routes.middleware', ['web', 'auth']))->prefix(config('chat.routes.prefix', 'messages'))->group(function () {
    Route::get('/', [ChatController::class, 'index'])->name('chat.index');
    Route::get('/conversations', [ChatController::class, 'conversations'])->name('chat.conversations');
    Route::get('/conversations/{conversation}/messages', [ChatController::class, 'messages'])->name('chat.messages');
    Route::post('/messages', [ChatController::class, 'store'])->name('chat.store')->middleware(EnsureChatUser::class);
});
