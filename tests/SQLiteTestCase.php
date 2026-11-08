<?php

namespace Tests;

use Illuminate\Support\Facades\DB;

abstract class SQLiteTestCase extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        if (! app()->environment('testing')) {
            throw new \RuntimeException(
                'SQLiteTestCase can only run in the testing environment.'
            );
        }

        config()->set('database.connections.sqlite.database', ':memory:');
        config()->set('database.default', 'sqlite');

        DB::purge('sqlite');
        DB::setDefaultConnection('sqlite');
        DB::reconnect('sqlite');

        if (
            DB::connection()->getDriverName() !== 'sqlite' ||
            DB::connection()->getDatabaseName() !== ':memory:'
        ) {
            throw new \RuntimeException(
                'SQLiteTestCase failed to initialize an in-memory SQLite connection.'
            );
        }
    }

    protected function tearDown(): void
    {
        DB::disconnect('sqlite');

        parent::tearDown();
    }
}