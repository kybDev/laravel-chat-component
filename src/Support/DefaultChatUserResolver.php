<?php

namespace Vendor\LaravelChat\Support;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Vendor\LaravelChat\Contracts\ChatUserResolver;

class DefaultChatUserResolver implements ChatUserResolver
{
    public function resolve(Request $request): mixed
    {
        if (Auth::check()) {
            return Auth::user();
        }

        if (method_exists($request, 'user') && $request->user()) {
            return $request->user();
        }

        $requestUser = $request->attributes->get('user');
        if (is_object($requestUser) && isset($requestUser->user_id)) {
            $model = $this->resolveUserModel();

            return $model ? $model->find($requestUser->user_id) : null;
        }

        $acl = $request->attributes->get('acl');
        if (is_object($acl) && isset($acl->user_id)) {
            $model = $this->resolveUserModel();

            return $model ? $model->find($acl->user_id) : null;
        }

        if (is_array($acl ?? null) && isset($acl['user_id'])) {
            $model = $this->resolveUserModel();

            return $model ? $model->find($acl['user_id']) : null;
        }

        return null;
    }

    protected function resolveUserModel(): mixed
    {
        $class = config('chat.auth.user_model', config('auth.providers.users.model', \App\Models\User::class));

        return class_exists($class) ? new $class() : null;
    }
}
