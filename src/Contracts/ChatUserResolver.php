<?php

namespace Vendor\LaravelChat\Contracts;

use Illuminate\Http\Request;

interface ChatUserResolver
{
    /**
     * Resolve the current application user for chat activity.
     */
    public function resolve(Request $request): mixed;
}
