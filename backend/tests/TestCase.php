<?php

namespace Tests;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    // Every web request runs HandleInertiaRequests::share (nav categories query),
    // so HTTP tests always need a migrated database.
    use RefreshDatabase;
}
