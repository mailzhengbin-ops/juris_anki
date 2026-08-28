<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureAdmin
{
    /**
     * 管理端权限隔离：非管理员一律 403。
     */
    public function handle(Request $request, Closure $next): Response
    {
        abort_unless((bool) $request->user()?->is_admin, 403);

        return $next($request);
    }
}
