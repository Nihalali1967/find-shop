<?php

namespace App\Http\Middleware;

use App\Models\Admin;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureApiAdmin
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();
        $token = $user?->currentAccessToken();

        if (! $user instanceof Admin || ! $token || ! in_array('admin', (array) $token->abilities, true)) {
            return response()->json(['message' => 'Administrator access is required.'], 403);
        }

        return $next($request);
    }
}
