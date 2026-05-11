<?php

namespace HyperfTest;

use Hyperf\DbConnection\Db;
use Hyperf\Testing\TestCase;

class DatabaseTestCase extends TestCase
{
    protected array $tablesToClean = [];

    protected function setUp(): void
    {
        parent::setUp();
        Db::statement('SET FOREIGN_KEY_CHECKS=0');
        foreach ($this->tablesToClean as $table) {
            Db::table($table)->truncate();
        }
        Db::statement('SET FOREIGN_KEY_CHECKS=1');
    }
}