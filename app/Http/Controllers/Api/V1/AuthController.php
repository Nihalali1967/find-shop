<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Shop;
use App\Models\User;
use App\Services\Otp\OtpService;
use App\Services\Sms\FakeSmsGateway;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class AuthController extends Controller
{
    /**
     * Request OTP for authentication
     *
     * @bodyParam phone string required Phone number (max 20 chars). Example: +919876543210
     * @bodyParam purpose string required Purpose of OTP request. Must be one of: client_login, shop_login. Example: client_login
     * @response 200 {
     *   "data": {
     *     "challenge_id": "uuid",
     *     "expires_at": "2024-01-01T12:00:00Z",
     *     "resend_available_in": 60,
     *     "dev_code": "123456"
     *   }
     * }
     */
    public function requestOtp(Request $request, OtpService $otp): JsonResponse
    {
        $data = $request->validate([
            'phone' => ['required', 'string', 'max:20'],
            'purpose' => ['required', Rule::in(['client_login', 'shop_login'])],
        ]);

        $challenge = $otp->request($data['phone'], $data['purpose'], $request->ip(), (string) $request->userAgent());

        $payload = [
            'challenge_id' => $challenge->challenge_id,
            'expires_at' => $challenge->expires_at->toIso8601String(),
            'resend_available_in' => $challenge->resendSecondsRemaining(),
        ];

        // The fake gateway is strictly local/testing; it never leaks into production.
        if (config('services.sms.driver') === 'fake' && app()->environment(['local', 'testing'])) {
            $payload['dev_code'] = FakeSmsGateway::last()['code'] ?? null;
        }

        return response()->json(['data' => $payload]);
    }

    /**
     * Verify OTP and authenticate user
     *
     * @bodyParam challenge_id string required Challenge ID from requestOtp. Example: uuid
     * @bodyParam phone string required Phone number (max 20 chars). Example: +919876543210
     * @bodyParam purpose string required Purpose of OTP request. Must be one of: client_login, shop_login. Example: client_login
     * @bodyParam code string required OTP code (max 10 chars). Example: 123456
     * @response 200 {
     *   "data": {
     *     "token": "token_string",
     *     "abilities": ["client"],
     *     "user": {
     *       "id": 1,
     *       "phone": "+919876543210",
     *       "name": "John Doe"
     *     }
     *   }
     * }
     * @response 422 {
     *   "message": "This code was not issued for that number.",
     *   "code": "OTP_PHONE_MISMATCH"
     * }
     */
    public function verifyOtp(Request $request, OtpService $otp): JsonResponse
    {
        $data = $request->validate([
            'challenge_id' => ['required', 'string', 'max:64'],
            'phone' => ['required', 'string', 'max:20'],
            'purpose' => ['required', Rule::in(['client_login', 'shop_login'])],
            'code' => ['required', 'string', 'max:10'],
        ]);

        $challenge = $otp->verify($data['challenge_id'], $data['code'], $data['purpose']);

        if ($challenge->phone !== \App\Support\NameNormalizer::phone($data['phone'])) {
            return response()->json(['message' => 'This code was not issued for that number.', 'code' => 'OTP_PHONE_MISMATCH'], 422);
        }

        $user = User::firstOrCreate(['phone' => $challenge->phone], ['phone_verified_at' => now()]);
        $user->forceFill(['phone_verified_at' => now()])->save();

        $abilities = ['client'];

        if ($data['purpose'] === 'shop_login') {
            $shop = Shop::withTrashed()->where('owner_id', $user->id)->first();
            $abilities = ['shop'];

            if ($shop && ! $shop->isActive()) {
                $abilities = ['shop:restricted'];
            } elseif (! $shop) {
                $abilities = ['shop:onboarding'];
            }
        }

        $token = $user->createToken('api', $abilities, now()->addDays(30))->plainTextToken;

        return response()->json([
            'data' => [
                'token' => $token,
                'abilities' => $abilities,
                'user' => ['id' => $user->id, 'phone' => $user->phone, 'name' => $user->name],
            ],
        ]);
    }

    /**
     * Get current authenticated user info
     *
     * @response 200 {
     *   "data": {
     *     "id": 1,
     *     "phone": "+919876543210",
     *     "name": "John Doe",
     *     "email": "john@example.com",
     *     "capabilities": {
     *       "can_browse": true,
     *       "can_chat": true,
     *       "owns_shop": true,
     *       "shop_status": "active",
     *       "can_manage_shop": true
     *     }
     *   }
     * }
     */
    public function me(Request $request): JsonResponse
    {
        $user = $request->user();
        $shop = Shop::withTrashed()->where('owner_id', $user->id)->first();

        return response()->json([
            'data' => [
                'id' => $user->id,
                'phone' => $user->phone,
                'name' => $user->name,
                'email' => $user->email,
                'capabilities' => [
                    'can_browse' => true,
                    'can_chat' => true,
                    'owns_shop' => (bool) $shop,
                    'shop_status' => $shop?->status,
                    'can_manage_shop' => (bool) $shop?->isActive(),
                ],
            ],
        ]);
    }

    /**
     * Logout current user (revoke token)
     *
     * @response 200 {
     *   "data": {
     *     "revoked": true
     *   }
     * }
     */
    public function logout(Request $request): JsonResponse
    {
        $request->user()->currentAccessToken()?->delete();

        return response()->json(['data' => ['revoked' => true]]);
    }
}
