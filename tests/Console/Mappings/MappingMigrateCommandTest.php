<?php

namespace Tests\Console\Mappings;

use DesignMyNight\Elasticsearch\Console\Mappings\Exceptions\FailedToPutNewMapping;
use DesignMyNight\Elasticsearch\Console\Mappings\MappingMigrateCommand;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\RequestException;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Psr7\Response;
use Illuminate\Filesystem\Filesystem;
use Mockery as m;
use Symfony\Component\Finder\SplFileInfo;
use Tests\TestCase;

/**
 * Class MappingMigrateCommandTest
 *
 * @package Tests\Console\Mappings
 */
class MappingMigrateCommandTest extends TestCase
{

    /** @var m\CompositeExpectation|MappingMigrateCommand */
    private $command;

    public function setUp()
    {
        parent::setUp();

        $this->command = m::mock(MappingMigrateCommand::class)
            ->shouldAllowMockingProtectedMethods()
            ->makePartial();

        $this->command->setHost('host:1111');
        $this->command->setConnection(collect());
        $this->command->files = m::mock(Filesystem::class);
    }

    /**
     * It returns the mapping migration files.
     *
     * @test
     * @covers MappingMigrateCommand::getMappingFiles()
     */
    public function it_returns_the_mapping_migration_files()
    {
        $files = [];

        $this->command->files->shouldReceive('files')->once()->andReturn($files);

        $this->assertEquals($files, $this->command->getMappingFiles());
    }

    /**
     * It gets the mapping name.
     *
     * @test
     * @covers       MappingMigrateCommand::getMappingName()
     * @dataProvider get_mapping_name_data_provider
     */
    public function it_gets_the_mapping_name($expected, $withSuffix = false)
    {
        config(['database.connections.elasticsearch.suffix' => '_dev']);

        $this->assertEquals($expected, $this->command->getMappingName('mapping_filename.json', $withSuffix));
    }

    /**
     * @return array
     */
    public function get_mapping_name_data_provider():array
    {
        return [
            'removes file extension' => ['mapping_filename'],
            'appends suffix'         => ['mapping_filename_dev', true]
        ];
    }

    /**
     * It returns an array of mapping migrations that have not yet been migrated.
     *
     * @test
     * @covers MappingMigrateCommand::pendingMappings()
     */
    public function it_returns_an_array_of_mapping_migrations_that_have_not_yet_been_migrated()
    {
        $pending1 = new SplFileInfo('pending_file1.json', '', '');
        $pending2 = new SplFileInfo('pending_file2.json', '', '');

        $files = [
            new SplFileInfo('migrated_file1.json', '', ''),
            new SplFileInfo('migrated_file2.json', '', ''),
            new SplFileInfo('migrated_file3.json', '', ''),
            $pending1,
            $pending2,
        ];

        $migrations = [
            'migrated_file1',
            'migrated_file2',
            'migrated_file3',
        ];

        $expected = [$pending1, $pending2];

        $this->assertEquals($expected, $this->command->pendingMappings($files, $migrations));
    }

    /**
     * It puts the new mapping into the Elasticsearch cluster.
     *
     * @test
     * @covers MappingMigrateCommand::putMapping()
     */
    public function it_puts_the_new_mapping_into_the_elasticsearch_cluster()
    {
        $expected = ['acknowledged' => true];

        /** @var m\CompositeExpectation|SplFileInfo $mapping */
        $mapping = m::mock(SplFileInfo::class);
        $mapping->shouldReceive('getFileName')->andReturn('pending_mapping.json');
        $mapping->shouldReceive('getContents')->andReturn(json_encode(['mappings' => []]));

        $mock = new MockHandler([
            new Response(200, [], json_encode($expected)),
            new Response(400, ['Content-Type' => 'application/json'], json_encode([
                'error'  => [
                    'reason' => 'index [pending_mapping] already exists'
                ],
                'status' => 400
            ]))
        ]);
        $handler = HandlerStack::create($mock);

        $this->command->client = new Client(['handler' => $handler]);

        $this->assertEquals($expected, $this->command->putMapping($mapping));

        $this->expectException(FailedToPutNewMapping::class);

        $this->command->putMapping($mapping);
    }

    /**
     * It writes a message to console if there are no mappings to migrate.
     *
     * @test
     * @covers MappingMigrateCommand::runPending()
     */
    public function it_writes_a_message_to_console_if_there_are_no_mappings_to_migrate()
    {
        $this->command->shouldReceive('info')->once()->with('No new mappings to migrate.');
        $this->command->shouldReceive('putMapping')->times(0);
        $this->command->shouldReceive('migrateMapping')->times(0);
        $this->command->shouldReceive('call')->times(0);
        $this->command->shouldReceive('updateAlias')->times(0);

        $this->command->runPending([]);
    }

    /**
     * It runs the pending migrations.
     *
     * @test
     * @covers       MappingMigrateCommand::runPending()
     * @dataProvider run_pending_data_provider
     */
    public function it_runs_the_pending_migrations($options)
    {
        /** @var m\CompositeExpectation|SplFileInfo $mapping */
        $mapping = m::mock(SplFileInfo::class);
        $mapping->shouldReceive('getFileName')->andReturn('pending_mapping.json');

        $pending = [$mapping];

        $this->command->shouldReceive('info');

        if ($options['put_mapping_fails']) {
            $this->command
                ->shouldReceive('putMapping')
                ->once()
                ->with($mapping)
                ->andThrow(new FailedToPutNewMapping(['error' => ['reason' => ''], 'status' => 400]));
            $this->command->shouldReceive('error')->once();

            $this->command->runPending($pending);

            return;
        }

        $this->command->shouldReceive('putMapping')->once()->with($mapping);
        $this->command->shouldReceive('migrateMapping')->once()->with(1, 'pending_mapping');

        $command = $options['has_artisan_command'] ? 'index:local-command' : 'index:mapping';

        $this->command
            ->shouldReceive('argument')
            ->once()
            ->with('artisan-command')
            ->andReturn($command);

        $this->command
            ->shouldReceive('call')
            ->once()
            ->with($command, ['index' => 'pending_mapping']);

        $this->command
            ->shouldReceive('option')
            ->once()
            ->with('swap')
            ->andReturn($options['swap_alias']);

        if ($options['swap_alias']) {
            $this->command
                ->shouldReceive('updateAlias')
                ->once()
                ->with('pending_mapping_dev');
        }

        $this->command->runPending($pending);
    }

    /**
     * @return array
     */
    public function run_pending_data_provider():array
    {
        $defaults = [
            'has_artisan_command' => false,
            'put_mapping_fails'   => false,
            'swap_alias'          => false
        ];

        return [
            'put mapping fails'         => [array_merge($defaults, ['put_mapping_fails' => true])],
            'artisan command is passed' => [array_merge($defaults, ['has_artisan_command' => true])],
            'automatically swap alias'  => [array_merge($defaults, ['swap_alias' => true])]
        ];
    }

    /**
     * @param \Illuminate\Foundation\Application $app
     */
    protected function getEnvironmentSetUp($app)
    {
        $app['config']->set('laravel-elasticsearch.index_command', 'index:mapping');

        parent::getEnvironmentSetUp($app);
    }
}