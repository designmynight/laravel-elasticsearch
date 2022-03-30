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
        //default laravel/elasticsearch configurations
        $app['config']->set('database.connections.elasticsearch', [
            'driver' => 'elasticsearch',
            'host' => 'localhost',
            'port' => 9200,
            'scheme' => 'http',
            'database' => 'database',
            'username' => 'admin',
            'password' => 'admin',
            'suffix' => '_dev',
            'sslVerification' => true,
        ]);

        //default package configurations
        $app['config']->set('elasticsearch', [
            'defaultConnection' => 'default',
            'default' => [
                'sslVerification' => true,
            ],
        ]);
    }

    /**
     * @return void
     */
    public function tearDown(): void
    {
        parent::tearDown();
    }
}
