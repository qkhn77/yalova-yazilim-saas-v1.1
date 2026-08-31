<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Support\Facades\Cache;

abstract class TestCase extends BaseTestCase
{
    protected function tearDown(): void
    {
        // RefreshDatabase resets rows and IDs, while scoped domain services
        // can otherwise retain per-firma caches between tests in the same
        // PHPUnit process. Never carry one test's tenant/module decision into
        // the next isolated database state.
        $this->app?->forgetScopedInstances();
        Cache::flush();

        parent::tearDown();
    }
}
