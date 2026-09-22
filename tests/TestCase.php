<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    /**
     * Act as a Sanctum token. Guards are forgotten first because the auth manager
     * is a singleton and would otherwise reuse the previously resolved identity.
     */
    protected function withApiToken(string $token): static
    {
        $this->app['auth']->forgetGuards();

        return $this->withToken($token);
    }
}
