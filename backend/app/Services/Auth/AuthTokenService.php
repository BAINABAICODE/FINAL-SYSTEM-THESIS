<?php

namespace App\Services\Auth;

use App\Models\AuthToken;
use App\Models\User;
use App\Support\Auth\LoginRules;
use Illuminate\Support\Str;

class AuthTokenService
{
    public function issue(User $user): string
    {
        $user->authTokens()->where('expires_at', '<=', now())->delete();

        $plain = Str::random(64);

        $user->authTokens()->create([
            'token_hash' => $this->hash($plain),
            'expires_at' => now()->addDays(LoginRules::TOKEN_DAYS),
        ]);

        return $plain;
    }

    public function findUser(string $plain): ?User
    {
        $token = AuthToken::query()
            ->where('token_hash', $this->hash($plain))
            ->where('expires_at', '>', now())
            ->first();

        if ($token === null) {
            return null;
        }

        $token->forceFill(['last_used_at' => now()])->save();

        return $token->user;
    }

    public function revoke(string $plain): void
    {
        AuthToken::query()->where('token_hash', $this->hash($plain))->delete();
    }

    private function hash(string $plain): string
    {
        return hash('sha256', $plain);
    }
}
