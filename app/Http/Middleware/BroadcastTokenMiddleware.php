<?php

namespace App\Http\Middleware;

use App\Models\User;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class BroadcastTokenMiddleware
{
    /** @var array<string, User|null> */
    private static array $usersByToken = [];

    public function handle(Request $request, Closure $next): Response
    {
        $token = $request->bearerToken();

        if (! $token) {
            return response()->json(['error' => 'Token requerido'], 401);
        }

        $hash = hash('sha256', $token);

        if (! array_key_exists($hash, self::$usersByToken)) {
            self::$usersByToken[$hash] = User::where('broadcast_token', $hash)->first();
        }

        $user = self::$usersByToken[$hash];

        if (! $user) {
            return response()->json(['error' => 'Token inválido'], 401);
        }

        Auth::setUser($user);

        return $next($request);
    }
}
