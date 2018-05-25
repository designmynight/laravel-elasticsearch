<?php

namespace Tests\Console\Mappings;

use DesignMyNight\Elasticsearch\Console\Mappings\IndexRemoveCommand;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\RequestException;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Psr7\Request;
use GuzzleHttp\Psr7\Response;
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
     * @dataProvider remove_index_data_provider
     */
    public function it_removes_the_given_index($expected, $request)
    {
        $mock = new MockHandler([$request]);
        $handler = HandlerStack::create($mock);

        $this->command->client = new Client(['handler' => $handler]);

        $this->command->shouldReceive('info');

        if ($expected === false) {
            $this->command->shouldReceive('error')->once();
        }

        $this->assertEquals($expected, $this->command->removeIndex('test_index'));
    }

    /**
     * @return array
     */
    public function remove_index_data_provider():array
    {
        return [
            '200 response'      => [true, new Response(200, [], json_encode(['acknowledged' => true]))],
            'request exception' => [false, new RequestException('', new Request('delete', ''))]
        ];
    }

    /**
     * It handles the console command call.
     *
     * @test
     * @covers IndexRemoveCommand::handle()
     */
    public function it_handles_the_console_command_call()
    {
        $index = 'some_index';
        $this->command->shouldReceive('argument')->once()->with('index')->andReturn($index);
        $this->command->shouldReceive('confirm')->withAnyArgs()->andReturnTrue();
        $this->command->shouldReceive('removeIndex')->once()->with($index);

        $this->command->handle();
    }

    /**
     * It handles the console command call when in production.
     *
     * @test
     * @covers IndexRemoveCommand::handle()
     */
    public function it_handles_the_console_command_call_when_in_production()
    {
        $index = 'some_index';
        $this->command->shouldReceive('argument')->once()->with('index')->andReturn($index);
        $this->command->shouldReceive('confirm')->withAnyArgs()->andReturnFalse();
        $this->command->shouldReceive('removeIndex')->never();

        $this->command->handle();
    }

    /**
     * @param \Illuminate\Foundation\Application $app
     */
    protected function getEnvironmentSetUp($app)
    {
        $app['env'] = 'production';
    }
}