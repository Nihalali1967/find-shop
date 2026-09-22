<?php

namespace App\Services\Otp;

use App\Exceptions\MarketplaceException;
use App\Models\OnboardingGrant;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class OnboardingGrantService
{
    public const PURPOSE_SHOP_REGISTRATION = 'shop_registration';

    public const PURPOSE_SHOP_CLAIM = 'shop_claim';

    public const PURPOSE_PHONE_CHANGE = 'phone_change';

    /**
     * @return array{grant: OnboardingGrant, token: string}
     */
    public function issue(User $user, string $purpose, array $payload = [], int $ttlMinutes = 30): array
    {
        $token = Str::random(64);

        $grant = OnboardingGrant::create([
            'user_id' => $user->id,
            'purpose' => $purpose,
            'token_hash' => hash('sha256', $token),
            'payload' => $payload,
            'expires_at' => now()->addMinutes($ttlMinutes),
        ]);

        return ['grant' => $grant, 'token' => $token];
    }

    /**
     * Redeem a grant token once and return the grant record.
     */
    public function redeem(string $token, string $purpose): OnboardingGrant
    {
        return DB::transaction(function () use ($token, $purpose) {
            $grant = OnboardingGrant::query()
                ->where('token_hash', hash('sha256', $token))
                ->where('purpose', $purpose)
                ->lockForUpdate()
                ->first();

            if (! $grant || ! $grant->isUsable()) {
                throw MarketplaceException::invalid('This setup link has expired. Please verify your mobile again.', 'GRANT_INVALID');
            }

            $grant->forceFill(['consumed_at' => now()])->save();

            return $grant;
        });
    }

    public function find(string $token, string $purpose): ?OnboardingGrant
    {
        return OnboardingGrant::query()
            ->where('token_hash', hash('sha256', $token))
            ->where('purpose', $purpose)
            ->whereNull('consumed_at')
            ->where('expires_at', '>', now())
            ->first();
    }
}
