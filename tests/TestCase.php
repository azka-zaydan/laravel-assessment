<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Support\Facades\Schema;
use Laravel\Passport\ClientRepository;

abstract class TestCase extends BaseTestCase
{
    /**
     * Seed a Passport personal access client when running against a fresh
     * in-memory SQLite database. RefreshDatabase wipes the schema each test
     * class, so this must run after parent::setUp() (which runs migrations).
     */
    protected function setUp(): void
    {
        parent::setUp();
        $this->ensurePassportClient();
    }

    private function ensurePassportClient(): void
    {
        // Only run when the oauth_clients table exists (i.e. migrations ran).
        if (! Schema::hasTable('oauth_clients')) {
            return;
        }

        try {
            // Attempt to resolve the existing personal access client.
            app(ClientRepository::class)->personalAccessClient(
                config('auth.guards.api.provider', 'users')
            );
        } catch (\RuntimeException) {
            // None found — create one.
            app(ClientRepository::class)->createPersonalAccessGrantClient(
                'Test Personal Access Client'
            );
        }
    }
}
