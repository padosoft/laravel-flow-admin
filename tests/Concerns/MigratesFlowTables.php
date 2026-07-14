<?php

declare(strict_types=1);

namespace Padosoft\LaravelFlowAdmin\Tests\Concerns;

use Illuminate\Support\Facades\DB;

/**
 * Boots a throwaway SQLite database and runs core's (`padosoft/laravel-flow`)
 * migrations against it, for Feature tests that exercise real
 * `DefinitionRepository`/`FlowDashboardReadModel` persistence rather than
 * the `array` demo adapter. Call `setUpFlowDatabase()` from `setUp()` and
 * `tearDownFlowDatabase()` from `tearDown()`.
 */
trait MigratesFlowTables
{
    private string $flowDatabasePath;

    private function setUpFlowDatabase(): void
    {
        $this->flowDatabasePath = tempnam(sys_get_temp_dir(), 'lfa-flow-db-') . '.sqlite';
        touch($this->flowDatabasePath);

        $this->app['config']->set('database.default', 'sqlite');
        $this->app['config']->set('database.connections.sqlite.database', $this->flowDatabasePath);
        DB::purge('sqlite');
        DB::statement('PRAGMA foreign_keys = ON');

        $this->migrateFlowTables();
    }

    private function tearDownFlowDatabase(): void
    {
        DB::disconnect('sqlite');

        if (isset($this->flowDatabasePath) && file_exists($this->flowDatabasePath)) {
            unlink($this->flowDatabasePath);
        }
    }

    private function migrateFlowTables(): void
    {
        $directory = $this->resolveMigrationDirectory();
        $files = glob($directory . DIRECTORY_SEPARATOR . '*.php') ?: [];
        sort($files);

        foreach ($files as $file) {
            $this->runMigration($file);
        }
    }

    private function resolveMigrationDirectory(): string
    {
        $candidates = [
            dirname(__DIR__, 2) . DIRECTORY_SEPARATOR . 'vendor/padosoft/laravel-flow/database/migrations',
            base_path('vendor/padosoft/laravel-flow/database/migrations'),
            dirname(base_path(), 1) . DIRECTORY_SEPARATOR . 'vendor/padosoft/laravel-flow/database/migrations',
        ];

        foreach ($candidates as $candidate) {
            if (is_dir($candidate)) {
                return $candidate;
            }
        }

        $this->fail('Migration directory not found for padosoft/laravel-flow');

        return '';
    }

    private function runMigration(string $path): void
    {
        if (! file_exists($path)) {
            $this->fail('Migration file not found: ' . $path);
        }

        $migration = require $path;
        $migration->up();
    }
}
