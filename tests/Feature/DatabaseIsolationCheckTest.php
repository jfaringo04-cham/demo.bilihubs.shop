<?php

namespace Tests\Feature;

use Tests\SQLiteTestCase;

class DatabaseIsolationCheckTest extends SQLiteTestCase
{
    public function test_database_is_sqlite_in_memory(): void
    {
        $this->assertSame('testing', app()->environment());
        $this->assertSame('sqlite', config('database.default'));
        $this->assertSame(':memory:', config('database.connections.sqlite.database'));
        $this->assertSame('sqlite', \Illuminate\Support\Facades\DB::connection()->getDriverName());
    }
}
