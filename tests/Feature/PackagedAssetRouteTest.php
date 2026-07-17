<?php

declare(strict_types=1);

namespace Padosoft\LaravelFlowAdmin\Tests\Feature;

use Illuminate\Testing\TestResponse;
use Padosoft\LaravelFlowAdmin\Tests\TestCase;
use RuntimeException;

/**
 * Pins the catch-all packaged-asset route (`/_flow-admin/assets/{file}`) that
 * serves Vite's shared/hashed chunks (e.g. the React runtime chunk shared by
 * the Studio and Monitor islands): it streams a real built file with the
 * right Content-Type, 404s when the file is absent, and refuses to escape the
 * built `assets/` directory.
 *
 * The package's OWN `public/vendor/flow-admin/` is moved aside for the
 * duration of each test and restored in tearDown(), so a real local build is
 * never touched.
 */
final class PackagedAssetRouteTest extends TestCase
{
    private string $buildDir = '';

    private ?string $backupDir = null;

    protected function setUp(): void
    {
        parent::setUp();

        $this->buildDir = dirname(__DIR__, 2) . '/public/vendor/flow-admin';

        if (! is_dir($this->buildDir)) {
            return;
        }

        $backupDir = $this->buildDir . '.phpunit-backup-' . getmypid() . '-' . uniqid('', true);

        if (! rename($this->buildDir, $backupDir)) {
            throw new RuntimeException("Failed to back up {$this->buildDir}.");
        }

        $this->backupDir = $backupDir;
    }

    protected function tearDown(): void
    {
        if (is_dir($this->buildDir)) {
            $this->deleteDirectory($this->buildDir);
        }

        if ($this->backupDir !== null && ! rename($this->backupDir, $this->buildDir)) {
            throw new RuntimeException("Failed to restore the real build from {$this->backupDir}.");
        }

        parent::tearDown();
    }

    public function test_serves_a_built_chunk_with_a_javascript_content_type(): void
    {
        $this->seedChunk('jsx-runtime-ABC123.js', 'export const x = 1;');

        $response = $this->get('/_flow-admin/assets/jsx-runtime-ABC123.js');

        $response->assertStatus(200);
        $response->assertHeader('Content-Type', 'application/javascript; charset=utf-8');
        $this->assertStringContainsString('export const x', $this->streamedBody($response));
    }

    public function test_returns_404_for_a_missing_chunk(): void
    {
        $response = $this->get('/_flow-admin/assets/does-not-exist-XYZ.js');

        $response->assertStatus(404);
    }

    public function test_refuses_to_escape_the_assets_directory(): void
    {
        // `..` matches the route's [A-Za-z0-9._-]+ constraint, so the realpath
        // guard (not just the route) must reject the traversal.
        $this->seedChunk('real.js', 'ok');

        $response = $this->get('/_flow-admin/assets/..');

        $response->assertStatus(404);
    }

    private function seedChunk(string $file, string $contents): void
    {
        $assets = $this->buildDir . '/assets';

        if (! is_dir($assets) && ! mkdir($assets, 0o777, true) && ! is_dir($assets)) {
            throw new RuntimeException("Failed to create {$assets}.");
        }

        file_put_contents($assets . '/' . $file, $contents);
    }

    private function streamedBody(TestResponse $response): string
    {
        ob_start();
        $response->baseResponse->sendContent();

        return (string) ob_get_clean();
    }

    private function deleteDirectory(string $directory): void
    {
        if (! is_dir($directory)) {
            return;
        }

        foreach (scandir($directory) ?: [] as $entry) {
            if ($entry === '.' || $entry === '..') {
                continue;
            }

            $path = $directory . '/' . $entry;
            is_dir($path) ? $this->deleteDirectory($path) : @unlink($path);
        }

        @rmdir($directory);
    }
}
