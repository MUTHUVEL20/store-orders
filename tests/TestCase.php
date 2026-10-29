<?php

namespace Tests;

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use RuntimeException;

abstract class TestCase extends BaseTestCase
{
    /**
     * Boot the application, but refuse to continue unless it points at a
     * dedicated test database. RefreshDatabase and the concurrency tests wipe
     * tables, so this guard must run before any test trait is set up.
     */
    public function createApplication(): Application
    {
        // The framework's own implementation requires bootstrap/app.php and bootstraps the
        // console kernel; reuse it so internal test-lifecycle bookkeeping is not skipped.
        $app = parent::createApplication();

        $database = (string) $app['config']->get('database.connections.mysql.database');

        if (! str_ends_with($database, '_test')) {
            throw new RuntimeException(sprintf(
                'Refusing to run the tests against database "%s": its name must end with "_test". '
                .'Check DB_DATABASE in phpunit.xml.',
                $database,
            ));
        }

        return $app;
    }
}
