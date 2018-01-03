<?php

namespace DesignMyNight\Elasticsearch;

use Illuminate\Support\ServiceProvider;

class ElasticsearchServiceProvider extends ServiceProvider {
  /**
   * Register the service provider.
   */
  public function register(){
    // Add database driver.
    $this->app->resolving('db', function ($db) {
      $db->extend('elasticsearch', function ($config) {
        return new Connection($config);
      });
    });
  }
}
