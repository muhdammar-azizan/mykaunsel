<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Support\Facades\DB;
use RuntimeException;

abstract class TestCase extends BaseTestCase
{
    /**
     * The tests wipe the database they run on (RefreshDatabase). On MySQL that must be a throw-away database, never a real one, so a
     * database whose name does not contain "test" is refused BEFORE anything is touched. (SQLite in memory is the normal run.)
     */
    protected function setUpTraits()
    {
        $connection = config('database.default');
        $driver = config("database.connections.{$connection}.driver");
        $database = (string) config("database.connections.{$connection}.database");

        if ($driver !== 'sqlite' && ! str_contains(strtolower($database), 'test')) {
            throw new RuntimeException("Refusing to run the tests on the database \"{$database}\": they delete everything in it. Use a throw-away database whose name contains \"test\" (docs/MYSQL_TESTING.md).");
        }

        return parent::setUpTraits();
    }

    /**
     * A query as plain text, the same on SQLite ("table"), MySQL/MariaDB (`table`) and PostgreSQL ("table"): identifier quotes are
     * removed. A test that looks at the SQL of a query must look at this, never at the quoted text, or it only passes on one database.
     */
    protected static function plainSql(string $sql): string
    {
        return str_replace(['`', '"'], '', $sql);
    }
}
