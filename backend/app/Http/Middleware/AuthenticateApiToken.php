<?php

namespace App\Http\Middleware;

use App\Services\Auth\AuthTokenService;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class AuthenticateApiToken
{
    public function __construct(private readonly AuthTokenService $tokens) {}

    public function handle(Request $request, Closure $next): Response
    {
        $plain = $request->bearerToken();
        $user = is_string($plain) && $plain !== '' ? $this->tokens->findUser($plain) : null;

        if ($user === null) {
            return response()->json(['message' => 'Unauthenticated.'], 401);
        }

        Auth::setUser($user);
        $request->setUserResolver(static fn () => $user);

        return $next($request);
    }
}
