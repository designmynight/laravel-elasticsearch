<?php

namespace Tests;


class TestCase extends \Orchestra\Testbench\TestCase
{

    /**
     * Set up the tests.
     */
    public function setUp(): void
    {
        parent::setUp();
    }

    protected function defineEnvironment($app)
    {
        $app['config']->set('database.connections.elasticsearch.suffix', '_dev');
    }

    /**
     * @return void
     */
    public function tearDown(): void
    {
        parent::tearDown();
    }
}
