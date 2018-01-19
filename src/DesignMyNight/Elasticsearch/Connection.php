<?php

namespace DesignMyNight\Elasticsearch;

use Closure;
use Elasticsearch\ClientBuilder;
use Illuminate\Database\Connection as BaseConnection;
use Illuminate\Database\Grammar as BaseGrammar;

class Connection extends BaseConnection
{
    /**
     * The Elasticsearch client.
     *
     * @var \Elasticsearch\Client
     */
    protected $connection;

    protected $indexSuffix = '';

    /**
     * Create a new Elasticsearch connection instance.
     *
     * @param array $config
     */
    public function __construct(array $config)
    {
        $this->config = $config;

        $this->indexSuffix = isset($config['suffix']) ? $config['suffix'] : '';

        // Extract the hosts from config
        $hostsConfig = $config['hosts'] ?? $config['host'];
        $hosts = explode(',', $hostsConfig);

        // You can pass options directly to the client
        $options = array_get($config, 'options', []);

        // Create the connection
        $this->connection = $this->createConnection($hosts, $config, $options);

        $this->useDefaultQueryGrammar();
        $this->useDefaultPostProcessor();
    }

    /**
     * Create a new Elasticsearch connection.
     *
     * @param array  $hosts
     * @param array  $config
     *
     * @return \Elasticsearch\Client
     */
    protected function createConnection($hosts, array $config, array $options)
    {
        return ClientBuilder::create()
            ->setHosts($hosts)
            ->setSelector('\Elasticsearch\ConnectionPool\Selectors\StickyRoundRobinSelector')
            ->build();
    }

    /**
     * Get the default query grammar instance.
     *
     * @return \Illuminate\Database\Query\Grammars\Grammar
     */
    protected function getDefaultQueryGrammar()
    {
        return $this->withIndexSuffix(new QueryGrammar);
    }

    /**
     * Set the table prefix and return the grammar.
     *
     * @param  \Illuminate\Database\Grammar  $grammar
     * @return \Illuminate\Database\Grammar
     */
    public function withIndexSuffix(BaseGrammar $grammar)
    {
        $grammar->setIndexSuffix($this->indexSuffix);

        return $grammar;
    }

    /**
     * Get the default post processor instance.
     *
     * @return Processor
     */
    protected function getDefaultPostProcessor()
    {
        return new QueryProcessor();
    }

    /**
     * Get the table prefix for the connection.
     *
     * @return string
     */
    public function getTablePrefix()
    {
        return $this->indexSuffix;
    }

    /**
     * Set the table prefix in use by the connection.
     *
     * @param  string  $prefix
     * @return void
     */
    public function setIndexSuffix($suffix)
    {
        $this->indexSuffix = $suffix;

        $this->getQueryGrammar()->setIndexSuffix($suffix);
    }

    /**
     * Begin a fluent query against a database table.
     *
     * @param  string  $table
     * @return \Illuminate\Database\Query\Builder
     */
    public function table($table)
    {

    }

    /**
     * Get a new raw query expression.
     *
     * @param  mixed  $value
     * @return \Illuminate\Database\Query\Expression
     */
    public function raw($value)
    {

    }

    /**
     * Run a select statement and return a single result.
     *
     * @param  string  $query
     * @param  array   $bindings
     * @return mixed
     */
    public function selectOne($query, $bindings = [])
    {

    }

    /**
     * Run a select statement against the database.
     *
     * @param  array   $params
     * @param  array   $bindings
     * @return array
     */
    public function select($params, $bindings = [])
    {
        return $this->connection->search($params);
    }

    /**
     * Run a select statement against the database using an Elasticsearch scroll cursor.
     *
     * @param  array   $params
     * @param  array   $bindings
     * @return array
     */
    public function scrollSelect($params, $bindings = [])
    {
        $scrollTimeout = '30s';

        $scrollParams = array(
            'scroll' => $scrollTimeout,
            'size'   => min($params['body']['size'], 5000),
            'index'  => $params['index'],
            'body'   => $params['body']
        );

        $results = $this->select($scrollParams);

        $scrollId = $results['_scroll_id'];

        $numFound = $results['hits']['total'];

        $numResults = count($results['hits']['hits']);

        if ( $params['body']['size'] > $numResults ){
            $results['scrollCursor'] = $this->scroll($scrollId, $scrollTimeout, $params['body']['size'] - $numResults);
        }

        return $results;
    }

    /**
     * Run a select statement against the database using an Elasticsearch scroll cursor.
     */
    public function scroll($scrollId, $scrollTimeout, $limit){
        $numResults = 0;

        // Loop until the scroll 'cursors' are exhausted or we have enough results
        while ($numResults < $limit) {
            // Execute a Scroll request
            $results = $this->connection->scroll(array(
                'scroll_id' => $scrollId,
                'scroll'    => $scrollTimeout,
            ));

            // Get new scroll ID in case it's changed
            $scrollId = $results['_scroll_id'];

            // Break if no results
            if (empty($results['hits']['hits'])) {
                break;
            }

            foreach ( $results['hits']['hits'] as $result ){
                $numResults++;

                if ( $numResults > $limit){
                    break;
                }

                yield $result;
            }
        }
    }

    /**
     * Run an insert statement against the database.
     *
     * @param  array  $query
     * @param  array   $bindings
     * @return bool
     */
    public function insert($params, $bindings = [])
    {
        return $this->connection->bulk($params);
    }

    /**
     * Run an update statement against the database.
     *
     * @param  string  $query
     * @param  array   $bindings
     * @return array
     */
    public function update($query, $bindings = [])
    {
        return $this->connection->index($query);
    }

    /**
     * Run a delete statement against the database.
     *
     * @param  string  $query
     * @param  array   $bindings
     * @return array
     */
    public function delete($query, $bindings = [])
    {
        return $this->connection->delete($query);
    }

    /**
     * Execute an SQL statement and return the boolean result.
     *
     * @param  string  $query
     * @param  array   $bindings
     * @return bool
     */
    public function statement($query, $bindings = [])
    {

    }

    /**
     * Run an SQL statement and get the number of rows affected.
     *
     * @param  string  $query
     * @param  array   $bindings
     * @return int
     */
    public function affectingStatement($query, $bindings = [])
    {

    }

    /**
     * Run a raw, unprepared query against the PDO connection.
     *
     * @param  string  $query
     * @return bool
     */
    public function unprepared($query)
    {

    }

    /**
     * Prepare the query bindings for execution.
     *
     * @param  array  $bindings
     * @return array
     */
    public function prepareBindings(array $bindings)
    {

    }

    /**
     * Execute a Closure within a transaction.
     *
     * @param  \Closure  $callback
     * @param  int  $attempts
     * @return mixed
     *
     * @throws \Throwable
     */
    public function transaction(Closure $callback, $attempts = 1)
    {

    }

    /**
     * Start a new database transaction.
     *
     * @return void
     */
    public function beginTransaction()
    {

    }

    /**
     * Commit the active database transaction.
     *
     * @return void
     */
    public function commit()
    {

    }

    /**
     * Rollback the active database transaction.
     *
     * @return void
     */
    public function rollBack()
    {

    }

    /**
     * Get the number of active transactions.
     *
     * @return int
     */
    public function transactionLevel()
    {

    }

    /**
     * Execute the given callback in "dry run" mode.
     *
     * @param  \Closure  $callback
     * @return array
     */
    public function pretend(Closure $callback)
    {

    }

    /**
     * Dynamically pass methods to the connection.
     *
     * @param string $method
     * @param array  $parameters
     *
     * @return mixed
     */
    public function __call($method, $parameters)
    {
        return call_user_func_array([$this->connection, $method], $parameters);
    }
}
