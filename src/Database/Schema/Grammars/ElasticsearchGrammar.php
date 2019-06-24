<?php

namespace DesignMyNight\Elasticsearch\Database\Schema\Grammars;

use DesignMyNight\Elasticsearch\Database\Schema\Blueprint;
use Illuminate\Database\Schema\Blueprint as BaseBlueprint;
use Illuminate\Database\Schema\Grammars\Grammar;
use Illuminate\Support\Fluent;

/**
 * Class Grammar
 * @package DesignMyNight\Elasticsearch\Database\Schema
 */
class ElasticsearchGrammar extends Grammar
{
    /** @var array */
    protected $modifiers = ['Boost', 'Dynamic', 'Fields', 'Format', 'Index', 'Properties'];

    /**
     * @inheritDoc
     */
    protected function addModifiers($sql, BaseBlueprint $blueprint, Fluent $property)
    {
        foreach ($this->modifiers as $modifier) {
            if (method_exists($this, $method = "modify{$modifier}")) {
                $property = $this->{$method}($blueprint, $property);
            }
        }

        return $property;
    }

    /**
     * @param BaseBlueprint $blueprint
     * @return array
     */
    protected function getColumns(BaseBlueprint $blueprint)
    {
        $columns = [];

        foreach ($blueprint->getAddedColumns() as $property) {
            // Pass empty string as we only need to modify the property and return it.
            $columns[] = $this->addModifiers('', $blueprint, $property);
        }

        return $columns;
    }

    /**
     * @param Blueprint $blueprint
     * @param Fluent    $property
     * @return Fluent
     */
    protected function modifyBoost(Blueprint $blueprint, Fluent $property)
    {
        if (!is_null($property->boost) && !is_numeric($property->boost)) {
            throw new \InvalidArgumentException('Boost modifier must be numeric', 400);
        }

        return $property;
    }

    /**
     * @param Blueprint $blueprint
     * @param Fluent    $property
     * @return Fluent
     */
    protected function modifyDynamic(Blueprint $blueprint, Fluent $property)
    {
        if (!is_null($property->dynamic) && !is_bool($property->dynamic)) {
            throw new \InvalidArgumentException('Dynamic modifier must be a boolean', 400);
        }

        return $property;
    }

    /**
     * @param Blueprint $blueprint
     * @param Fluent    $property
     * @return Fluent
     */
    protected function modifyFields(Blueprint $blueprint, Fluent $property)
    {
        if (!is_null($property->fields)) {
            $fields = $property->fields;
            $property->fields = $fields($blueprint);
        }

        return $property;
    }

    /**
     * @param Blueprint $blueprint
     * @param Fluent    $property
     * @return Fluent
     */
    protected function format(Blueprint $blueprint, Fluent $property)
    {
        if (!is_string($property->format)) {
            throw new \InvalidArgumentException('Format modifier must be a string', 400);
        }

        return $property;
    }

    /**
     * @param Blueprint $blueprint
     * @param Fluent    $property
     * @return Fluent
     */
    protected function modifyProperties(Blueprint $blueprint, Fluent $property)
    {
        if (!is_null($property->properties)) {
            $properties = $property->properties;
            $property->properties = $properties($blueprint);
        }

        return $property;
    }
}
