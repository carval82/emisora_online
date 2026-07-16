<?php

namespace App\Http\Middleware;

use App\Models\User;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class BroadcastTokenMiddleware
{
    public function handle(Request $request, Closure $next): Response
    {
        $token = $request->bearerToken();

        if (! $token) {
            return response()->json(['error' => 'Token requerido'], 401);
        }

        $user = User::where('broadcast_token', hash('sha256', $token))->first();

        if (! $user) {
            return response()->json(['error' => 'Token inválido'], 401);
        }

        Auth::setUser($user);

        return $next($request);
    }
}
