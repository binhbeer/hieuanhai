<?php

namespace App\Http\Middleware;

use App\Models\ApiKey;
use App\Models\User;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class VerifyAiApiKey
{
    /**
     * @param  Closure(Request): Response  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $token = $request->bearerToken();

        if (! is_string($token) || $token === '') {
            return $this->unauthorized();
        }

        $key = ApiKey::query()
            ->with('user')
            ->where('token_hash', ApiKey::hashToken($token))
            ->first();

        $user = $key?->user;

        if (! $key || ! $user instanceof User) {
            return $this->unauthorized();
        }

        if ($user->isBanned()) {
            return response()->json(['message' => 'This account has been suspended.'], 403);
        }

        $key->forceFill(['last_used_at' => now()])->save();

        Auth::setUser($user);
        $request->attributes->set('ai_api_key', $key);

        return $next($request);
    }

    private function unauthorized(): Response
    {
        return response()->json(['message' => 'The API key is invalid.'], 401);
    }
}
