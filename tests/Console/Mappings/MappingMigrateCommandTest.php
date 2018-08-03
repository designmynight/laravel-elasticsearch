<?php

namespace Tests\Console\Mappings;

use DesignMyNight\Elasticsearch\Console\Mappings\Exceptions\FailedToPutNewMapping;
use DesignMyNight\Elasticsearch\Console\Mappings\MappingMigrateCommand;
use Elasticsearch\ClientBuilder;
use Elasticsearch\Namespaces\IndicesNamespace;
use GuzzleHttp\Client;
use Illuminate\Database\Query\Builder;
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

        $builder = m::mock(Builder::class);
        $builder->shouldReceive('orderBy')->andReturnSelf();
        $builder->shouldReceive('pluck')->andReturn(collect());
        $builder->shouldReceive('max')->andReturn(0);

        $this->command->setConnection($builder);
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
    public function it_gets_the_mapping_name($expected, $filename, $withSuffix = false)
    {
        config(['database.connections.elasticsearch.suffix' => '_dev']);

        $this->assertEquals($expected, $this->command->getMappingName($filename, $withSuffix));
    }

    /**
     * @return array
     */
    public function get_mapping_name_data_provider():array
    {
        return [
            'removes file extension' => ['mapping_filename', 'mapping_filename.json'],
            'appends suffix'         => ['mapping_filename_dev', 'mapping_filename.json', true],
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
     * @covers       MappingMigrateCommand::putMapping()
     * @dataProvider put_mapping_data_provider
     */
    public function it_puts_the_new_mapping_into_the_elasticsearch_cluster(string $method, string $file)
    {
        $contents = json_encode(['mappings' => []]);

        /** @var m\CompositeExpectation|SplFileInfo $mapping */
        $mapping = m::mock(SplFileInfo::class);
        $mapping->shouldReceive('getFileName')->andReturn($file);
        $mapping->shouldReceive('getContents')->andReturn($contents);

        $this->command->shouldReceive($method)->once();

        $this->command->putMapping($mapping);
    }

    /**
     * putMapping data provider.
     */
    public function put_mapping_data_provider():array
    {
        return [
            'create index' => ['createIndex', 'pending_mapping.json'],
            'update index' => ['updateIndex', 'update_pending_mappings.json'],
        ];
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
        $this->command->shouldReceive('putMapping')->never();
        $this->command->shouldReceive('migrateMapping')->never();
        $this->command->shouldReceive('call')->never();
        $this->command->shouldReceive('updateAlias')->never();

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
        $this->command->shouldReceive('putMapping')->once()->with($mapping);
        $this->command->shouldReceive('migrateMapping')->once()->with(1, 'pending_mapping');

        $this->command
            ->shouldReceive('option')
            ->once()
            ->with('index')
            ->andReturn($options['automatically_index']);

        if ($options['automatically_index']) {
            $this->command
                ->shouldReceive('index')
                ->once()
                ->with('pending_mapping');

            $this->command
                ->shouldReceive('option')
                ->atMost()
                ->once()
                ->with('swap')
                ->andReturn($options['swap_alias']);
        }

        $this->command->shouldReceive('call')
            ->once()
            ->with('make:mapping-alias', ['name' => 'pending_mapping', 'index' => 'pending_mapping']);

        if($options['automatically_index']){
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
            'swap_alias'          => false,
            'automatically_index' => false,
        ];

        return [
            'artisan command is passed'   => [array_merge($defaults, ['has_artisan_command' => true])],
            'automatically index mapping' => [array_merge($defaults, ['automatically_index' => true])],
            'automatically swap alias'    => [array_merge($defaults, ['automatically_index' => true, 'swap_alias' => true])],
        ];
    }

    /**
     * It handles the console command call.
     *
     * @test
     * @covers MappingMigrateCommand::handle()
     */
    public function it_handles_the_console_command_call()
    {
        $mappings = [
            new SplFileInfo('mapping_migration1.json', '', ''),
            new SplFileInfo('mapping_migration2.json', '', ''),
            new SplFileInfo('mapping_migration3.json', '', ''),
        ];

        $this->command->shouldReceive('getMappingFiles')->once()->andReturn($mappings);
        $this->command->shouldReceive('pendingMappings')->once()->with($mappings, [])->passthru();
        $this->command->shouldReceive('runPending')->once()->with($mappings);

        $this->command->handle();
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