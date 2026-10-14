<?php

namespace Vendor\LaravelChat\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Vendor\LaravelChat\Support\DefaultChatUserResolver;

class EnsureChatUser
{
    public function handle(Request $request, Closure $next)
    {
        $resolverClass = config('chat.auth.resolver', DefaultChatUserResolver::class);
        $resolver = app($resolverClass);

        $user = $resolver->resolve($request);
        if (! $user) {
            abort(401, 'Chat user could not be resolved.');
        }

        Auth::setUser($user);
        $request->setUserResolver(fn () => $user);

        return $next($request);
    }
}
