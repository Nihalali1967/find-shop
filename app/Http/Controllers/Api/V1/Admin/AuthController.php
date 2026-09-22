<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Models\Admin;
use App\Services\Audit\AuditLogger;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;

class AuthController extends Controller
{
    public function login(Request $request): JsonResponse
    {
        $data = $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required', 'string'],
        ]);

        $key = 'admin-api-login:'.$request->ip();

        if (RateLimiter::tooManyAttempts($key, 5)) {
            return response()->json([
                'message' => 'Too many attempts.',
                'retry_after' => RateLimiter::availableIn($key),
            ], 429);
        }

        $admin = Admin::where('email', $data['email'])->where('is_active', true)->first();

        if (! $admin || ! Hash::check($data['password'], $admin->password)) {
            RateLimiter::hit($key, 300);

            return response()->json(['message' => 'These credentials do not match our records.'], 401);
        }

        RateLimiter::clear($key);
        $admin->forceFill(['last_login_at' => now()])->save();

        $token = $admin->createToken('admin-api', ['admin'], now()->addHours(12))->plainTextToken;

        AuditLogger::log('admin.login', subject: $admin, actor: $admin, ip: $request->ip());

        return response()->json(['data' => ['token' => $token, 'admin' => ['id' => $admin->id, 'name' => $admin->name]]]);
    }

    public function logout(Request $request): JsonResponse
    {
        $request->user()->currentAccessToken()?->delete();

        return response()->json(['data' => ['revoked' => true]]);
    }
}
