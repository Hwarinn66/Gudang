<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    public function createApplication()
    {
        $app = parent::createApplication();
        if ($app['config']->get('database.connections.mysql.database') !== 'warehouse_stock_test') {
            throw new \RuntimeException('Tests only allowed on warehouse_stock_test.');
        }

        return $app;
    }
}
