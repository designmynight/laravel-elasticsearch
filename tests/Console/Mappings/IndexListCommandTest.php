<?php

namespace Tests\Console\Mappings;

use DesignMyNight\Elasticsearch\Console\Mappings\IndexListCommand;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\RequestException;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Psr7\Request;
use GuzzleHttp\Psr7\Response;
use Orchestra\Testbench\TestCase;
use Mockery as m;

/**
 * Class IndexListCommandTest
 *
 * @package Tests\Console\Mappings
 */
class IndexListCommandTest extends TestCase
{

    /** @var m\CompositeExpectation|IndexListCommand */
    private $command;

    /**
     * Set up tests.
     */
    public function setUp()
    {
        parent::setUp();

        $this->command = m::mock(IndexListCommand::class)
            ->shouldAllowMockingProtectedMethods()
            ->makePartial();

        $this->command->setHost('host:1111');
    }

    /**
     * It gets a list of indices on the Elasticsearch cluster.
     *
     * @test
     * @covers       IndexListCommand::getIndices()
     * @dataProvider get_indices_data_provider
     */
    public function it_gets_a_list_of_indices_on_the_elasticsearch_cluster($expected, $request)
    {
        $mock = new MockHandler([$request]);
        $handler = HandlerStack::create($mock);

        $this->command->client = new Client(['handler' => $handler]);

        if (is_null($expected)) {
            $this->command->shouldReceive('error')->once()->with('Failed to retrieve indices.');
        }

        $this->assertEquals($expected, $this->command->getIndices());
    }

    /**
     * @return array
     */
    public function get_indices_data_provider():array
    {
        return [
            '200 response'      => ['some response', new Response(200, [], 'some response')],
            'request exception' => [null, new RequestException('', new Request('GET', ''))]
        ];
    }

    /**
     * It returns a formatted array of active aliases and their corresponding indices.
     *
     * @test
     * @covers       IndexListCommand::getIndicesForAlias()
     * @dataProvider get_indices_for_alias_data_provider
     */
    public function it_returns_a_formatted_array_of_active_aliases_and_their_corresponding_indices($expected, $request)
    {
        $mock = new MockHandler([
            $request
        ]);
        $handler = HandlerStack::create($mock);

        $this->command->client = new Client(['handler' => $handler]);

        if ($expected === []) {
            $this->command->shouldReceive('error')->once()->with("Failed to retrieve alias *");
        }

        $this->assertEquals($expected, $this->command->getIndicesForAlias());
    }

    /**
     * @return array
     */
    public function get_indices_for_alias_data_provider():array
    {
        $body = [
            '2018_05_21_111500_test_production' => [
                'aliases' => [
                    'test_production' => []
                ]
            ],
            '2018_05_21_111500_test_dev'        => [
                'aliases' => [
                    'test_dev' => []
                ]
            ],
            '2017_05_21_111500_test_dev'        => [
                'aliases' => [
                    'test_dev' => []
                ]
            ],
        ];

        return [
            '200 response'      => [
                [
                    'test_dev'        => [
                        '2017_05_21_111500_test_dev',
                        '2018_05_21_111500_test_dev'
                    ],
                    'test_production' => [
                        '2018_05_21_111500_test_production'
                    ]
                ],
                new Response(200, ['Content-Type' => 'application/json'], json_encode($body))
            ],
            'request exception' => [[], new RequestException('', new Request('get', ''))]
        ];
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
            $alias => ['index1', 'index2', 'index3']
        ]);

        $this->command->shouldReceive('info')->once()->withAnyArgs();

        $this->command->shouldReceive('line')->times(4)->withAnyArgs();

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

        $indices = 'here are some indices';
        $this->command->shouldReceive('getIndices')->once()->andReturn($indices);
        $this->command->shouldReceive('line')->once()->with($indices);

        $this->command->handle();
    }
}
