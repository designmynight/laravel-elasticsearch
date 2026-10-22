<?php

namespace Tests\Integration;

use DesignMyNight\Elasticsearch\EloquentBuilder;
use DesignMyNight\Elasticsearch\Searchable;
use Illuminate\Database\Eloquent\Model;

class SearchableIntegrationModel extends Model
{
    use Searchable;

    protected $table = 'bookings';

    protected $connection = 'elasticsearch';

    protected $searchType = 'booking';

    protected $keyType = 'string';

    protected $indexAsChildDocuments = [];

    public $timestamps = false;

    public function newEloquentBuilder($query)
    {
        return new EloquentBuilder($query);
    }
}
