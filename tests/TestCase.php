<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Support\Facades\DB;

abstract class TestCase extends BaseTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->registerSqliteDateDiff();
    }

    /**
     * Query dashboard memakai DATEDIFF() MySQL, sedangkan test berjalan di SQLite.
     * Fungsi ini didaftarkan agar halaman dashboard bisa dirender di test.
     */
    private function registerSqliteDateDiff(): void
    {
        if (DB::connection()->getDriverName() !== 'sqlite') {
            return;
        }

        DB::connection()->getPdo()->sqliteCreateFunction('DATEDIFF', function ($start, $end) {
            $startDate = date_create((string) $start);
            $endDate = date_create((string) $end);

            if ($startDate === false || $endDate === false) {
                return null;
            }

            return (int) floor(($endDate->getTimestamp() - $startDate->getTimestamp()) / 86400);
        });
    }
}
