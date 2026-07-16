<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\URL;
use Symfony\Component\HttpFoundation\Response;

class UseRequestHost
{
    public function handle(Request $request, Closure $next): Response
    {
        if (app()->environment('local')) {
            URL::forceRootUrl($request->getSchemeAndHttpHost());
        } elseif (app()->environment('production')) {
            URL::forceScheme('https');
        }

        return $next($request);
    }
}
