<?php

namespace Tests\Unit\Database\Schema;

use Carbon\Carbon;
use DesignMyNight\Elasticsearch\Connection;
use DesignMyNight\Elasticsearch\Database\Schema\Blueprint;
use DesignMyNight\Elasticsearch\Database\Schema\Grammars\ElasticsearchGrammar;
use Mockery as m;
use Tests\TestCase;

class BlueprintTest extends TestCase
{
    private Blueprint $blueprint;

    public function setUp(): void
    {
        parent::setUp();

        $this->blueprint = new Blueprint('indices');
    }

    /**
     * @test
     */
    public function it_fails_if_grammar_no_method()
    {
        $connection = m::mock(Connection::class);

        $grammar = m::mock(ElasticsearchGrammar::class);

        $grammar->shouldReceive('getFluentCommands')
            ->once()
            ->andReturn([]);

        $fluent = $this->blueprint->dropUnique('test');

        $this->blueprint->date('created_at');

        $closure = function () {
        };

        $grammar->shouldReceive('compileDrop')
            ->never()
            ->with($this->blueprint, $fluent, $connection)
            ->andReturn($closure);

        $results = $this->blueprint->toSql($connection, $grammar);

        $this->assertEquals([], $results);
    }

    /**
     * @test
     */
    public function it_updates_mapping()
    {
        $connection = m::mock(Connection::class);

        $grammar = m::mock(ElasticsearchGrammar::class);

        $grammar->shouldReceive('getFluentCommands')
            ->once()
            ->andReturn([]);

        $fluent = $this->blueprint->update();
        $this->blueprint->date('created_at');

        $closure = function () {
        };

        $grammar->shouldReceive('compileUpdate')
            ->once()
            ->with($this->blueprint, $fluent, $connection)
            ->andReturn($closure);

        $results = $this->blueprint->toSql($connection, $grammar);

        $this->assertEquals([$closure], $results);
    }

    /**
     * @test
     */
    public function it_creates_mapping()
    {
        $connection = m::mock(Connection::class);

        $grammar = m::mock(ElasticsearchGrammar::class);

        $grammar->shouldReceive('getFluentCommands')
            ->once()
            ->andReturn([]);

        $fluent = $this->blueprint->create();
        $this->blueprint->date('created_at');

        $closure = function () {
        };

        $grammar->shouldReceive('compileCreate')
            ->once()
            ->with($this->blueprint, $fluent, $connection)
            ->andReturn($closure);

        $results = $this->blueprint->toSql($connection, $grammar);

        $this->assertEquals([$closure], $results);
    }

    /**
     * @test
     */
    public function it_performs_update()
    {
        $this->blueprint->update();

        if ($commands = $this->blueprint->getCommands()) {
            $attr = $commands[0]->getAttributes();
            $this->assertEquals(['name' => 'update'], $attr);
        }
    }

    /**
     * @test
     */
    public function it_performs_token_count()
    {
        $type = 'token_count';
        $name = 'name';
        $expected = compact('type', 'name');

        $this->assertColumn($expected);
    }

    /**
     * @test
     */
    public function it_adds_text()
    {
        $type = 'text';
        $name = 'name';
        $expected = compact('type', 'name');

        $this->assertColumn($expected);
    }

    /**
     * @test
     */
    public function it_adds_string()
    {
        $type = 'string';
        $name = 'name';
        $expected = compact('type', 'name');

        $this->assertColumn($expected);
    }

    /**
     * @test
     */
    public function it_routing_required()
    {
        $this->blueprint->routingRequired();

        $this->assertEquals(["_routing" => ["required" => true]], $this->blueprint->getMeta());

    }

    /**
     * @test
     */
    public function it_adds_a_range()
    {
        $type = 'range';
        $name = 'name';
        $expected = compact('type', 'name');

        $this->assertColumn($expected);
    }

    /**
     * @test
     */
    public function it_adds_a_integer_range()
    {
        $type = 'integer_range';
        $name = 'name';
        $expected = compact('type', 'name');

        $this->blueprint->integerRange($expected['name']);

        $columns = $this->blueprint->getColumns();
        $attr = $columns[0]->getAttributes();

        $this->assertEquals($expected, $attr);
    }

    /**
     * @test
     */
    public function it_adds_a_integer()
    {
        $type = 'integer';
        $name = 'name';
        $expected = compact('type', 'name');

        $this->assertColumn($expected);
    }

    public function assertColumn($expected)
    {
        $this->blueprint->addColumn($expected['type'], $expected['name']);

        $columns = $this->blueprint->getColumns();
        $attr = $columns[0]->getAttributes();

        $this->assertEquals($expected, $attr);
    }

    /**
     * @test
     */
    public function it_adds_a_column()
    {
        $type = 'string';
        $name = 'name';
        $expected = compact('type', 'name');

        $this->assertColumn($expected);
    }

    /**
     * @test
     */
    public function it_adds_index_settings()
    {
        $key = 'key';
        $value = ['value' => 'value'];

        $this->blueprint->addIndexSettings($key, $value);
        $settings = $this->blueprint->getIndexSettings();
        $this->assertEquals([$key => $value], $settings);
    }

    /**
     * @test
     */
    public function it_adds_binary()
    {
        $name = 'column';
        $type = 'binary';
        $this->blueprint->binary($name);

        $expected = compact('name', 'type');

        $this->assertColumn($expected);
    }

    /**
     * @test
     */
    public function it_adds_meta()
    {
        $key = 'key';
        $value = ['value' => 'value'];

        $this->blueprint->addMetaField($key, $value);
        $settings = $this->blueprint->getMeta();

        $this->assertEquals([$key => $value], $settings);
    }

    /**
     * It gets the index alias.
     *
     * @test
     * @covers       \DesignMyNight\Elasticsearch\Database\Schema\Blueprint::getAlias
     * @dataProvider get_alias_data_provider
     */
    public function it_gets_the_index_alias(string $expected, $alias = null)
    {
        if (isset($alias)) {
            $this->blueprint->alias($alias);
        }

        $this->assertEquals($expected, $this->blueprint->getAlias());
    }

    /**
     * getAlias data provider.
     */
    public function get_alias_data_provider(): array
    {
        return [
            'alias not provided' => ['indices_dev'],
            'alias provided' => ['alias_dev', 'alias'],
        ];
    }

    /**
     * It gets the document type.
     *
     * @test
     * @covers       \DesignMyNight\Elasticsearch\Database\Schema\Blueprint::getDocumentType
     * @dataProvider get_document_type_data_provider
     */
    public function it_gets_the_document_type(string $expected, $documentType = null)
    {
        if (isset($documentType)) {
            $this->blueprint->document($documentType);
        }

        $this->assertEquals($expected, $this->blueprint->getDocumentType());
    }

    /**
     * getDocumentType data provider.
     */
    public function get_document_type_data_provider(): array
    {
        return [
            'document not provided' => ['index'],
            'document provided' => ['document', 'document'],
        ];
    }

    /**
     * It generates an index name.
     *
     * @test
     * @covers \DesignMyNight\Elasticsearch\Database\Schema\Blueprint::getIndex
     */
    public function it_generates_an_index_name()
    {
        Carbon::setTestNow(Carbon::create(2019, 7, 2, 12));

        $this->assertEquals('2019_07_02_120000_indices_dev', $this->blueprint->getIndex());
    }

    /**
     * adds settings ready to be used
     *
     * @test
     */
    public function adds_settings_ready_to_be_used(): void
    {
        $settings = [
            'filter' => [
                'autocomplete_filter' => [
                    'type' => 'edge_ngram',
                    'min_gram' => 1,
                    'max_gram' => 20,
                ],
            ],
            'analyzer' => [
                'autocomplete' => [
                    'type' => 'custom',
                    'tokenizer' => 'standard',
                    'filter' => [
                        'lowercase',
                        'autocomplete_filter',
                    ],
                ],
            ],
        ];

        $this->blueprint->addIndexSettings('analysis', $settings);

        $this->assertSame(
            [
                'analysis' => $settings,
            ],
            $this->blueprint->getIndexSettings()
        );
    }
}
