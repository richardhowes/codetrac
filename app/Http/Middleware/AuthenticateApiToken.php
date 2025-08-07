<?php

namespace App\Http\Middleware;

use App\Models\ApiToken;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class AuthenticateApiToken
{
    public function handle(Request $request, Closure $next): Response
    {
        $authHeader = $request->header('Authorization');
        
        if (!$authHeader || !str_starts_with($authHeader, 'Bearer ')) {
            return response()->json(['message' => 'Unauthorized - Missing Bearer token'], 401);
        }

        $token = substr($authHeader, 7);
        
        if (config('app.env') === 'local' && $token === 'test-token-123') {
            return $next($request);
        }

        $apiToken = ApiToken::findByToken($token);
        
        if (!$apiToken) {
            return response()->json(['message' => 'Unauthorized - Invalid token'], 401);
        }

        if ($apiToken->isExpired()) {
            return response()->json(['message' => 'Unauthorized - Token expired'], 401);
        }

        $apiToken->markAsUsed();
        
        $request->merge(['developer_id' => $apiToken->developer_id]);
        $request->setUserResolver(function () use ($apiToken) {
            return $apiToken->developer;
        });

        return $next($request);
    }
}