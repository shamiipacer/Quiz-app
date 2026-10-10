<?php

namespace Tests;

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    public function createApplication(): Application
    {
        $app = parent::createApplication();
        $driver = config('database.default');
        $database = config("database.connections.{$driver}.database");
        if (! ($driver === 'sqlite' && $database === ':memory:') && ! ($driver === 'mysql' && $database === 'quiz_test')) {
            throw new \RuntimeException('Tests require SQLite :memory: or the isolated MySQL quiz_test database.');
        }

        return $app;
    }
}
