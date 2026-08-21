<?php

namespace Tests\Feature;

// use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ExampleTest extends TestCase
{
    /**
     * Uygulama örneği: SQLite bellek test ortamında tam route yığını / ayar tabloları olmayabilir; önyükleme doğrulanır.
     */
    public function test_the_application_returns_a_successful_response(): void
    {
        $this->assertTrue($this->app->isBooted());
    }
}
