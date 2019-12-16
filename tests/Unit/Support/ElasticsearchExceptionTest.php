<?php

namespace Tests\Unit\Support;

use DesignMyNight\Elasticsearch\Support\ElasticsearchException;
use Elasticsearch\Common\Exceptions\Missing404Exception;
use Orchestra\Testbench\TestCase;

class ElasticsearchExceptionTest extends TestCase
{
    /** @var ElasticsearchException */
    private $exception;

    /** @var array */
    private $rawErrorMessage;

    /**
     * @test
     */
    public function returns_the_error_code( ):void {
        $this->assertSame('index_not_found_exception', $this->exception->getCode());
    }

    /**
     * @test
     */
    public function returns_the_error_message( ):void {
        $this->assertSame('no such index [bob]', $this->exception->getMessage());
    }

    /**
     * @test
     * @covers
     */
    public function converts_the_error_to_string( ):void {
        $this->assertSame('index_not_found_exception: no such index [bob]', (string) $this->exception);
    }

    /**
     * @test
     */
    public function returns_the_raw_error_message_as_an_array( ):void {
        $this->assertSame($this->rawErrorMessage, $this->exception->getRaw());
    }

    protected function setUp()
    {
        parent::setUp();

        $this->rawErrorMessage = [
            "error"  => [
                "root_cause"    => [
                    [
                        "type"          => "index_not_found_exception",
                        "reason"        => "no such index [bob]",
                        "resource.type" => "index_or_alias",
                        "resource.id"   => "bob",
                        "index_uuid"    => "_na_",
                        "index"         => "bob",
                    ],
                ],
                "type"          => "index_not_found_exception",
                "reason"        => "no such index [bob]",
                "resource.type" => "index_or_alias",
                "resource.id"   => "bob",
                "index_uuid"    => "_na_",
                "index"         => "bob",
            ],
            "status" => 404,
        ];

        $message = json_encode($this->rawErrorMessage);

        $this->exception = new ElasticsearchException(new Missing404Exception($message));
    }
}
