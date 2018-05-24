<?php
/**
 * Created by PhpStorm.
 * User: jasparguptalocal
 * Date: 23/05/2018
 * Time: 17:14
 */

namespace Tests;

use DesignMyNight\Elasticsearch\Console\Mappings\IndexRollbackCommand;
use Illuminate\Database\Query\Builder;
use Mockery as m;

/**
 * Class IndexRollbackCommandTest
 * @coversDefaultClass \DesignMyNight\Elasticsearch\Console\Mappings\IndexRollbackCommand
 * @package Tests
 */
class IndexRollbackCommandTest extends TestCase
{

    /** @var m\CompositeExpectation|IndexRollbackCommand */
    private $command;

    /**
     * Set up unit tests.
     */
    public function setUp()
    {
        parent::setUp();

        $this->command = m::mock(IndexRollbackCommand::class)
            ->shouldAllowMockingProtectedMethods()
            ->makePartial();
    }

    /**
     * It maps the aliases into the migrations.
     * @test
     * @covers ::mapAliases()
     */
    public function it_maps_the_aliases_into_the_migrations()
    {
        $migrations = collect([
            ['mapping' => '2018_05_24_100200_test_mapping'],
            ['mapping' => '2018_05_24_100400_test_mapping'],
            ['mapping' => '2018_05_24_100500_test_mapping'],
        ]);

        $mappedMigrations = $this->command->mapAliases($migrations);
        $migration = $mappedMigrations->first();

        $this->assertEquals('test_mapping_dev', $migration['alias']);
    }

    /**
     * It rolls back the mapping migration.
     * @test
     * @covers ::rollback()
     * @dataProvider rollback_data_provider
     */
    public function it_rolls_back_the_mapping_migration($migration, $hasPreviousMisgration)
    {
        $previousMigrations = collect([
            ['alias' => '1_pending_dev', 'mapping' => '1_pending_dev'],
            ['alias' => '2_pending_dev', 'mapping' => '2_pending_dev'],
            ['alias' => '3_pending_dev', 'mapping' => '3_pending_dev'],
        ]);

        $this->command->setPreviousMigrations($previousMigrations);

        if ($hasPreviousMisgration) {
            $this->command->shouldReceive('info')->twice()->withAnyArgs();
            $this->command->shouldReceive('updateAlias')->once()->with('3_pending_dev', null, $migration['alias']);

            $this->command->rollback($migration);

            return;
        }

        $this->command->shouldReceive('warn')->once()->withAnyArgs();

        $this->command->rollback($migration);
    }

    /**
     * @return array
     */
    public function rollback_data_provider():array
    {
        return [
            'has previous migration'    => [['alias' => '3_pending_dev', 'mapping' => '3_pending_dev'], true],
            'has no previous migration' => [['alias' => '4_pending_dev', 'mapping' => '4_pending_dev'], false],
        ];
    }

    /**
     * It strips the timestamp from the given string.
     * @test
     * @covers ::stripTimestamp()
     */
    public function it_strips_the_timestamp_from_the_given_string()
    {
        $this->assertEquals('something', $this->command->stripTimestamp('2018_05_24_123456_something'));
    }

    /**
     * It handles the console command when there is nothing to rollback.
     * @test
     * @covers ::handle()
     */
    public function it_handles_the_console_command_when_there_is_nothing_to_rollback()
    {
        $migrations = collect([]);

        $builder = m::mock(Builder::class);
        $builder->shouldReceive('orderBy')->twice()->andReturnSelf();
        $builder->shouldReceive('get')->once()->andReturn($migrations);

        $this->command->setConnection($builder);

        $this->command->shouldReceive('info')->once()->with('Nothing to rollback.');

        $this->command->handle();
    }

    /**
     * It handles the console command.
     * @test
     * @covers ::handle()
     */
    public function it_handles_the_console_command()
    {
        $migrations = collect([
            ['batch' => 1, 'mapping' => '1_mapping_book'],
            ['batch' => 1, 'mapping' => '2_mapping_tree'],
            ['batch' => 2, 'mapping' => '3_mapping_car'],
            ['batch' => 2, 'mapping' => '4_mapping_book'],
            ['batch' => 3, 'mapping' => '5_mapping_book'],
            ['batch' => 3, 'mapping' => '6_mapping_car'],
            ['batch' => 3, 'mapping' => '6_mapping_train'],
        ]);

        $builder = m::mock(Builder::class);
        $builder->shouldReceive('orderBy')->twice()->andReturnSelf();
        $builder->shouldReceive('get')->once()->andReturn($migrations);
        $builder->shouldReceive('max')->once()->with('batch')->andReturn(3);
        $builder->shouldReceive('where')->andReturnSelf();
        $builder->shouldReceive('delete')->once();

        $this->command->setConnection($builder);

        $this->command->shouldReceive('mapAliases')->once()->with($migrations)->passthru();
        $this->command->shouldReceive('rollback')->times(3);
        $this->command->shouldReceive('info')->once()->with('Successfully rolled back.');

        $this->command->handle();
    }
}
