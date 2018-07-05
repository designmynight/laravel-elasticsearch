<?php

namespace DesignMyNight\Elasticsearch\Console\Mappings;

use Elasticsearch\ClientBuilder;
use Elasticsearch\ConnectionPool\Selectors\StickyRoundRobinSelector;
use Illuminate\Console\Command as BaseCommand;

/**
 * Class Command
 * @package DesignMyNight\Elasticsearch\Console\Mappings
 */
abstract class Command extends BaseCommand
{

    /** @var ClientBuilder $client */
    public $client;

    /**
     * Command constructor.
     *
     * @param ClientBuilder $client
     */
    public function __construct(ClientBuilder $client)
    {
        parent::__construct();

        $hosts = explode(',', config('hosts', config('host', [])));
        $this->client = $client->setHosts($hosts)->setSelector(StickyRoundRobinSelector::class)->build();
    }
}
