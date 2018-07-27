<?php

namespace Tests\Console\Mappings;

use DesignMyNight\Elasticsearch\Console\Mappings\IndexRemoveCommand;
use Elasticsearch\Client;
use Elasticsearch\ClientBuilder;
use Elasticsearch\Namespaces\CatNamespace;
use Elasticsearch\Namespaces\IndicesNamespace;
use Mockery as m;
use Orchestra\Testbench\TestCase;

/**
 * Class IndexRemoveCommandTest
 *
 * @package Tests\Console\Mappings
 */
class IndexRemoveCommandTest extends TestCase
{

    /** @var m\CompositeExpectation|IndexRemoveCommand */
    private $command;

    public function setUp()
    {
        parent::setUp();

        $this->command = m::mock(IndexRemoveCommand::class)
            ->shouldAllowMockingProtectedMethods()
            ->makePartial();
    }

    /**
     * It removes the given index.
     *
     * @test
     * @covers       IndexRemoveCommand::removeIndex()
     */
    public function it_removes_the_given_index()
    {
        $indicesNamespace = m::mock(IndicesNamespace::class);
        $indicesNamespace->shouldReceive('delete')->once()->with(['index' => 'test_index']);

        $client = m::mock(Client::class);
        $client->shouldReceive('indices')->andReturn($indicesNamespace);

        $this->command->client = $client;

        $this->command->shouldReceive('info');

        $this->assertTrue($this->command->removeIndex('test_index'));
    }

    /**
     * It handles the console command call.
     *
     * @test
     * @covers IndexRemoveCommand::handle()
     * @dataProvider handle_data_provider
     */
    public function it_handles_the_console_command_call($index)
    {
        $catNamespace = m::mock(CatNamespace::class);
        $catNamespace->shouldReceive('indices')->andReturn([['index' => $index]]);

        $client = m::mock(Client::class);
        $client->shouldReceive('cat')->andReturn($catNamespace);

        $this->command->client = $client;

        $this->command->shouldReceive('argument')->once()->with('index')->andReturn($index);
        $this->command->shouldReceive('choice')->with('Which index would you like to delete?', [$index]);
        $this->command->shouldReceive('confirm')->withAnyArgs()->andReturn(!!$index);
        $this->command->shouldReceive('removeIndex')->with($index);

        $this->command->handle();
    }

    /**
     * @return array
     */
    public function handle_data_provider():array
    {
        return [
            'index given'    => ['test_index'],
            'no index given' => [null],
        ];
    }
}