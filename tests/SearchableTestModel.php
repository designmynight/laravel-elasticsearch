<?php

namespace Tests;

use DesignMyNight\Elasticsearch\Searchable;
use Illuminate\Database\Eloquent\Model;

class SearchableTestModel extends Model
{
    use Searchable;

    protected $table = 'bookings';

    public $timestamps = false;
}
