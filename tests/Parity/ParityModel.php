<?php

namespace Tests\Parity;

use DesignMyNight\Elasticsearch\Searchable;
use Illuminate\Database\Eloquent\Model;

class ParityModel extends Model
{
    use Searchable;

    protected $table = 'bookings';

    protected $connection = 'elasticsearch';

    public $timestamps = false;
}
