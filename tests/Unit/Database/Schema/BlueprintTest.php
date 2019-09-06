<?php

namespace Tests\Unit\Database\Schema;

use Carbon\Carbon;
use DesignMyNight\Elasticsearch\Database\Schema\Blueprint;
use Tests\TestCase;

class BlueprintTest extends TestCase
{
    /** @var Blueprint */
    private $blueprint;

    public function setUp()
    {
        parent::setUp();

        $this->blueprint = new Blueprint('indices');
    }

    /**
     * It gets the index alias.
     * @test
     * @covers \DesignMyNight\Elasticsearch\Database\Schema\Blueprint::getAlias
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
            'alias provided' => ['alias_dev', 'alias']
        ];
    }

    /**
     * It gets the document type.
     * @test
     * @covers \DesignMyNight\Elasticsearch\Database\Schema\Blueprint::getDocumentType
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
     * @test
     * @covers \DesignMyNight\Elasticsearch\Database\Schema\Blueprint::getIndex
     */
    public function it_generates_an_index_name()
    {
        Carbon::setTestNow(
            Carbon::create(2019, 7, 2, 12)
        );

        $this->assertEquals('2019_07_02_120000_indices_dev', $this->blueprint->getIndex());
    }
}
