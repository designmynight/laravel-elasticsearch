<?php

namespace Tests\Unit\Console\Mappings;

use DesignMyNight\Elasticsearch\Console\Mappings\IndexListCommand;
use Elasticsearch\Client;
use Elasticsearch\Namespaces\CatNamespace;
use Mockery as m;
use Tests\TestCase;

/**
 * Class IndexListCommandTest
 *
 * @package Tests\Console\Mappings
 */
class IndexListCommandTest extends TestCase
{
    use \Mockery\Adapter\Phpunit\MockeryPHPUnitIntegration;

    /** @var m\CompositeExpectation|IndexListCommand */
    private $command;

    /**
     * Set up tests.
     */
    public function setUp(): void
    {
        parent::setUp();

        $this->command = m::mock(IndexListCommand::class)
            ->shouldAllowMockingProtectedMethods()
            ->makePartial();
    }

    /**
     * It gets a list of indices on the Elasticsearch cluster.
     *
     * @test
     * @covers       IndexListCommand::getIndices()
     */
    public function it_gets_a_list_of_indices_on_the_elasticsearch_cluster()
    {
        $catNamespace = m::mock(CatNamespace::class);
        $catNamespace->shouldReceive('indices')->andReturn([]);

        $client = m::mock(Client::class);
        $client->shouldReceive('cat')->andReturn($catNamespace);

        $this->command->client = $client;

        $this->assertEquals([], $this->command->indices());
    }

    /**
     * It returns a formatted array of active aliases and their corresponding indices.
     *
     * @test
     * @covers       IndexListCommand::getIndicesForAlias()
     */
    public function it_returns_a_formatted_array_of_active_aliases_and_their_corresponding_indices()
    {
        $expected = [
            [
                'index' => '2017_05_21_111500_test_dev',
                'alias' => 'test_dev',
            ],
            [
                'index' => '2018_05_21_111500_test_dev',
                'alias' => 'test_dev',
            ],
            [
                'index' => '2018_05_21_111500_test_production',
                'alias' => 'test_production',
            ],
        ];

        $body = [
            [
                'index' => '2017_05_21_111500_test_dev',
                'alias' => 'test_dev',
            ],
            [
                'index' => '2018_05_21_111500_test_production',
                'alias' => 'test_production',
            ],
            [
                'index' => '2018_05_21_111500_test_dev',
                'alias' => 'test_dev',
            ],
        ];

        $catNamespace = m::mock(CatNamespace::class);
        $catNamespace->shouldReceive('aliases')->andReturn($body);

        $client = m::mock(Client::class);
        $client->shouldReceive('cat')->andReturn($catNamespace);

        $this->command->client = $client;

        $this->assertEquals($expected, $this->command->getIndicesForAlias());
    }

    /**
     * It handles the console command when an alias is given.
     *
     * @test
     * @covers IndexListCommand::handle()
     */
    public function it_handles_the_console_command_when_an_alias_is_given()
    {
        $alias = 'some_alias';
        $this->command->shouldReceive('option')->once()->with('alias')->andReturn($alias);

        $this->command->shouldReceive('getIndicesForAlias')->once()->with($alias)->andReturn([
            ['index' => 'index1', 'alias' => 'alias1'],
            ['index' => 'index2', 'alias' => 'alias2'],
            ['index' => 'index3', 'alias' => 'alias3'],
        ]);

        $this->command->shouldReceive('table')->once()->withAnyArgs();

        $this->command->handle();
    }

    /**
     * It handles the console command call.
     *
     * @test
     * @covers IndexListCommand::handle()
     */
    public function it_handles_the_console_command_call()
    {
        $this->command->shouldReceive('option')->once()->with('alias')->andReturnNull();

        $indices = [
            [
                'index' => 'name of index',
            ],
        ];
        $this->command->shouldReceive('indices')->once()->andReturn($indices);
        $this->command->shouldReceive('table')->once()->withAnyArgs();

        $this->command->handle();
    }
}
