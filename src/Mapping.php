<?php

namespace DesignMyNight\Elasticsearch;

use Illuminate\Database\Eloquent\Model;

/**
 * Class Mapping
 *
 * @package DesignMyNight\Elasticsearch
 */
class Mapping extends Model
{

    /** @var bool $timestamps */
    public $timestamps = false;

    /** @var array $casts */
    protected $casts = [
        'batch' => 'int'
    ];

    /** @var array $fillable */
    protected $fillable = ['batch', 'mapping'];
}
