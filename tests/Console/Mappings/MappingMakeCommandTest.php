<?php

namespace Tests\Console\Mappings;

use Carbon\Carbon;
use DesignMyNight\Elasticsearch\Console\Mappings\MappingMakeCommand;
use Mockery as m;
use Tests\TestCase;

/**
 * Class MappingMakeCommandTest
 *
 * @package Tests\Console\Mappings
 */
class MappingMakeCommandTest extends TestCase
{

    /** @var m\CompositeExpectation|MappingMakeCommand $command */
    private $command;

    /**
     * Set up tests.
     */
    public function setUp()
    {
        parent::setUp();

        $this->command = m::mock(MappingMakeCommand::class)
            ->shouldAllowMockingProtectedMethods()
            ->makePartial();
    }

    /**
     * It returns a timestamp prefixed stub of the name argument.
     *
     * @test
     * @covers MappingMakeCommand::getStub()
     */
    public function it_returns_a_timestamp_prefixed_stub_of_the_name_argument()
    {
        Carbon::setTestNow(Carbon::create(2018, 5, 18, 16, 12, 0));
        $this->command->shouldReceive('argument')->once()->andReturn('mapping');

        $this->assertEquals('2018_05_18_161200_mapping', $this->command->getStub());
    }

    /**
     * It returns the path of the new mapping file.
     *
     * @test
     * @covers MappingMakeCommand::getPath()
     */
    public function it_returns_the_path_of_the_new_mapping_file()
    {
        $this->command->shouldReceive('getStub')->once()->andReturn('2018_05_18_161600');

        $this->assertEquals(base_path('database/mappings/2018_05_18_161600.json'), $this->command->getPath());
    }

    /**
     * It returns the template path for the mapping to be created from.
     *
     * @test
     * @covers       MappingMakeCommand::getTemplate()
     * @dataProvider get_template_data_provider
     */
    public function it_returns_the_template_path_for_the_mapping_to_be_created_from($expected, $template)
    {
        $this->command->shouldReceive('option')->with('template')->once()->andReturn($template);

        $this->assertEquals(base_path($expected), $this->command->getTemplate());
    }

    /**
     * getTemplate data provider.
     */
    public function get_template_data_provider():array
    {
        return [
            'returns path for given template and appends ".json" extension if missing' => ['database/mappings/old_mapping.json', 'old_mapping'],
            'returns path for given template filename'                                 => ['database/mappings/old_mapping.json', 'old_mapping.json'],
            'returns default template path'                                            => ['vendor/designmynight/laravel-elasticsearch/src/Console/Mappings/stubs/mapping.stub', null],
        ];
    }
}
