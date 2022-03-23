<?php

namespace Tests\Unit\Support;

use DesignMyNight\Elasticsearch\Support\ElasticsearchException;
use Elasticsearch\Common\Exceptions\ElasticsearchException as BaseElasticsearchException;
use Elasticsearch\Common\Exceptions\Missing404Exception;

class ElasticsearchExceptionTest extends \PHPUnit\Framework\TestCase
{
    /**
     * @test
     * @dataProvider errorMessagesProvider
     */
    public function returns_the_error_code(BaseElasticsearchException $exception, string $code): void
    {
        $exception = new ElasticsearchException($exception);

        $this->assertSame($code, $exception->getCode());
    }

    /**
     * @test
     * @dataProvider errorMessagesProvider
     */
    public function returns_the_error_message(
        BaseElasticsearchException $exception,
        string $code,
        string $message
    ): void {
        $exception = new ElasticsearchException($exception);

        $this->assertSame($message, $exception->getMessage());
    }

    /**
     * @test
     * @dataProvider errorMessagesProvider
     */
    public function converts_the_error_to_string(
        BaseElasticsearchException $exception,
        string $code,
        string $message
    ): void {
        $exception = new ElasticsearchException($exception);

        $this->assertSame("$code: $message", (string)$exception);
    }

    /**
     * @test
     * @dataProvider errorMessagesProvider
     */
    public function returns_the_raw_error_message_as_an_array(
        BaseElasticsearchException $exception,
        string $code,
        string $message,
        array $raw
    ): void
    {
        $exception = new ElasticsearchException($exception);

        $this->assertSame($raw, $exception->getRaw());
    }

    public function errorMessagesProvider(): array
    {
        $missingIndexError = json_encode(
            [
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
            ]
        );

        return [
            'missing_index' => [
                'error'   => new Missing404Exception($missingIndexError),
                'code'    => 'index_not_found_exception',
                'message' => 'no such index [bob]',
                'raw'     => json_decode($missingIndexError, true),
            ],
        ];
    }
}
