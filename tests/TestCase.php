<?php

namespace Tests;

use Carbon\Carbon;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Schema;

abstract class TestCase extends BaseTestCase
{
    use DatabaseTransactions;
    use TestAuth;
    use TestHttp;

    protected static $migrated = false;

    /**
     * Setup the test environment
     */
    protected function setUp(): void
    {
        parent::setUp();

        if (! Schema::hasTable('roles')) {
            $this->artisan('migrate');
        }

        Carbon::setTestNow('2025-03-03T13:00:00.000Z');

        Http::preventStrayRequests();
    }

    /**
     * Make user can access route protected by middleware : password.confirm.
     */
    public function confirmPassword(): void
    {
        $this->withSession(['auth.password_confirmed_at' => time()]);
    }
}
