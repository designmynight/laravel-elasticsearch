# laravel-elasticsearch
Use Elasticsearch as a database in Laravel to retrieve Eloquent models and perform aggregations.

Build Elasticsearch queries as you're used to with Eloquent, and get Model instances in return, with some nice extras:
- Use `query`, `filter` and `postFilter` query types
- Perform geo searches
- Build and perform complex aggregations on your data
- Use the Elasticsearch scroll API to retrieve large numbers of results 

Note that this package doesn't provide any support for indexing documents in Elasticsearch - that's handled well by other libraries, for example [Laravel Scout](https://laravel.com/docs/master/scout) with the [ErickTamayo/laravel-scout-elastic](https://github.com/ErickTamayo/laravel-scout-elastic) driver.

## Setup
Add elasticsearch connection configuration to database.php
```
'elasticsearch' => [
    'driver'   => 'elasticsearch',
    'host'     => 'localhost',
    'port'     => 9200,
    'database' => 'your_es_index',
    'username' => 'optional_es_username',
    'password' => 'optional_es_username',
    'suffix'   => 'optional_es_index_suffix',
]
```

Create or update your base Model.php class to override `newEloquentBuilder()` and `newBaseQueryBuilder()`:

```PHP
/**
 * Create a new Eloquent builder for the model.
 *
 * @param  \Illuminate\Database\Query\Builder  $query
 * @return \Illuminate\Database\Eloquent\Builder|static
 */
public function newEloquentBuilder($query)
{
    switch ($this->getConnectionName()) {
        case 'elasticsearch':
            $builder = new ElasticsearchEloquentBuilder($query);
            break;

        default:
            $builder = new Illuminate\Database\Eloquent\Builder($query);
    }

    return $builder;
}

/**
 * Get a new query builder instance for the connection.
 *
 * @return \Illuminate\Database\Query\Builder
 */
protected function newBaseQueryBuilder()
{
    $connection = $this->getConnection();

    switch ($this->getConnectionName()) {
        case 'elasticsearch':
            $builder = new ElasticsearchQueryBuilder($connection, $connection->getQueryGrammar(), $connection->getPostProcessor());
            break;

        default:
            $builder = new Illuminate\Database\Query\Builder($connection, $connection->getPostProcessor());
    }

    return $builder;
}
```

## Search
You're now ready to carry out searches on your data. The query will look for an Elasticsearch index with the same name as the database table that your models reside in.

```PHP
$documents = MyModel::on('elasticsearch')
              ->where('date', '>', Carbon\Carbon::now())
              ->get();
```

## Aggregations
Aggregations can be added to a query with an approach that's similar to querying Elasticsearch directly, using nested functions rather than nested arrays. The `aggregation()` method takes three or four arguments:
1. A key to be used for the aggregation
2. The type of aggregation, such as 'filter' or 'terms'
3. (Optional) A callback or array providing options for the aggregation
4. (Optional) A function allowing you to provide further sub-aggregations 

```PHP
$myQuery = MyModel::on('elasticsearch')
             ->aggregation(
                 // The key of the aggregation (used in the Elasticsearch response)
                 'my_filter_aggregation',

                 // The type of the aggregation
                 'filter',

                 // A callback providing options to the aggregation, in this case adding filter criteria to a query builder
                 function ($query) {
                     $query->where('lost', '!=', true);
                     $query->where('concierge', true);
                 },

                 // A callback specifying a sub-aggregation
                 function ($builder) {
                     // A simpler aggregation, counting terms in the 'status' field
                     $builder->aggregation('my_terms_aggregation', 'terms', ['field' => 'status']);
                 }
             );

$results = $myQuery->get();
$aggregations = $myQuery->getQuery()->getAggregationResults();
```

## Geo queries
You can filter search results by distance from a geo point or include only those results that fall within given bounds, passing arguments in the format you'd use if querying Elasticsearch directly.

```PHP
$withinDistance = MyModel::on('elasticsearch')
                    ->whereGeoDistance('geo_field', [$lat, $lon], $distance);

$withinBounds= MyModel::on('elasticsearch')
                 ->whereGeoBoundsIn('geo_field', $boundingBox);
```

## Scroll API
You can use a scroll search to retrieve large numbers of results. Rather than returning a Collection, you'll get a PHP [Generator](http://php.net/manual/en/language.generators.overview.php) function that you can iterate over, where each value is a Model for a single result from Elasticsearch.

```PHP
$documents = MyModel::on('elasticsearch')
               ->limit(100000)
               ->usingScroll()
               ->get();

// $documents is a Generator
foreach ($documents as $document){
  echo $document->id;
}
```
