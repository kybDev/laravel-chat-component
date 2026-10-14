<?php

use Illuminate\Support\Facades\Route;
use Vendor\LaravelChat\Http\Controllers\ChatController;
use Vendor\LaravelChat\Http\Middleware\EnsureChatUser;

Route::prefix('api/' . config('chat.routes.prefix', 'messages'))->middleware(config('chat.routes.middleware', ['api', 'auth:api']))->group(function () {
    Route::get('/conversations', [ChatController::class, 'conversations'])->name('chat.api.conversations');
    Route::post('/messages', [ChatController::class, 'store'])->name('chat.api.store')->middleware(EnsureChatUser::class);
});
