<?php

namespace Tests;

use Carbon\Carbon;
use Orchestra\Testbench\TestCase as BaseTestCase;

class TestCase extends BaseTestCase
{

    /**
     * @return void
     */
    public function tearDown()
    {
        parent::tearDown();
        Carbon::setTestNow();
    }
}