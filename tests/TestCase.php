<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Support\Facades\Http;

abstract class TestCase extends BaseTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        Http::preventStrayRequests();
        config(['riot.key' => 'test-key', 'riot.requests_per_second' => 10000, 'riot.requests_per_two_minutes' => 10000]);
    }
}
