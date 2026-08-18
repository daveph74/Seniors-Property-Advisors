<?php

namespace App\Http\Middleware;

use App\Auth\Permissions;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class Permit
{
    public function handle(Request $request, Closure $next, string $ability): Response
    {
        $user = $request->user();

        if ($user === null) {
            return $request->expectsJson()
                ? response()->json(['message' => 'Unauthenticated.'], 401)
                : redirect()->guest(route('login'));
        }

        abort_unless($user->is_active, 403, 'This account has been deactivated.');
        abort_unless(Permissions::allows($user, $ability), 403);

        return $next($request);
    }
}
