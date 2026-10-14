<?php

namespace Vendor\LaravelChat\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;
use Vendor\LaravelChat\Services\ChatWorkspaceService;

class EnsureAuthenticatedChatUser
{
    public function handle(Request $request, Closure $next): Response
    {
        abort_unless(Auth::check(), 401);

        return app(EnsureChatUser::class)->handle($request, function (Request $request) use ($next) {
            if ($request->routeIs('chat.index')) {
                app(ChatWorkspaceService::class)->ensurePrivateNotes(
                    $request->user()->getAuthIdentifier(),
                );
            }

            return $next($request);
        });
    }
}
