<?php

declare(strict_types=1);

namespace Padosoft\LaravelFlowAdmin\Tests\Feature;

use Illuminate\Testing\TestResponse;
use Padosoft\LaravelFlowAdmin\Tests\TestCase;

/**
 * Pins `AbstractManifestAssetController`'s two behaviours: serving the
 * currently-built Studio JS/CSS resolved through `.vite/manifest.json`,
 * and degrading to a 404 (not a fatal error) when no build exists yet —
 * the state every fresh checkout starts in, since `public/vendor/flow-admin/`
 * is git-ignored and this suite must pass without `npm run build` having
 * run (that's the `e2e` CI job's job, not this PHP-only one).
 *
 * The package's OWN `public/vendor/flow-admin/` directory (not a
 * Testbench/consumer public path) is swapped out for a controlled fixture
 * for the duration of each test and restored in `tearDown()`, so this
 * suite never depends on — or clobbers — a developer's real local build.
 */
final class StudioAssetRouteTest extends TestCase
{
    private string $buildDir;

    private ?string $backupDir = null;

    protected function setUp(): void
    {
        parent::setUp();

        $this->buildDir = dirname(__DIR__, 2) . '/public/vendor/flow-admin';

        if (! is_dir($this->buildDir)) {
            return;
        }

        $backupDir = $this->buildDir . '.phpunit-backup-' . getmypid() . '-' . uniqid('', true);

        if (is_dir($backupDir)) {
            throw new \RuntimeException("Stale backup directory already exists: {$backupDir}");
        }

        if (! rename($this->buildDir, $backupDir)) {
            throw new \RuntimeException("Failed to back up {$this->buildDir} to {$backupDir} — refusing to risk the real build.");
        }

        $this->backupDir = $backupDir;
    }

    protected function tearDown(): void
    {
        if (is_dir($this->buildDir)) {
            $this->deleteDirectory($this->buildDir);
        }

        if ($this->backupDir !== null && ! rename($this->backupDir, $this->buildDir)) {
            throw new \RuntimeException("Failed to restore the real build from {$this->backupDir} to {$this->buildDir} — it is still there, restore it manually.");
        }

        parent::tearDown();
    }

    public function test_studio_js_returns_404_when_no_build_exists(): void
    {
        // The state of every fresh checkout: public/vendor/flow-admin/ is
        // git-ignored and `npm run build` has not run. Must degrade to a
        // clean 404, not a fatal error that would 500 the whole page.
        $response = $this->get('/_flow-admin/assets/studio.js');

        $response->assertStatus(404);
    }

    public function test_studio_css_returns_404_when_no_build_exists(): void
    {
        $response = $this->get('/_flow-admin/assets/studio.css');

        $response->assertStatus(404);
    }

    public function test_studio_js_is_served_with_javascript_content_type_when_built(): void
    {
        $this->seedFixtureBuild();

        $response = $this->get('/_flow-admin/assets/studio.js');

        $response->assertStatus(200);
        $response->assertHeader('Content-Type', 'application/javascript; charset=utf-8');
        $this->assertStringContainsString('window.__studioFixture', $this->extractStreamedBody($response));
    }

    public function test_studio_css_is_served_with_css_content_type_when_built(): void
    {
        $this->seedFixtureBuild();

        $response = $this->get('/_flow-admin/assets/studio.css');

        $response->assertStatus(200);
        $response->assertHeader('Content-Type', 'text/css; charset=utf-8');
        $this->assertStringContainsString('.studio-fixture', $this->extractStreamedBody($response));
    }

    public function test_studio_js_response_emits_revalidatable_cache_headers(): void
    {
        $this->seedFixtureBuild();

        $response = $this->get('/_flow-admin/assets/studio.js');

        $response->assertStatus(200);
        $cacheControl = $response->headers->get('Cache-Control');
        $this->assertStringContainsString('must-revalidate', (string) $cacheControl);
        $this->assertStringContainsString('max-age=300', (string) $cacheControl);
        $response->assertHeader('Last-Modified');
    }

    public function test_studio_js_returns_304_when_if_modified_since_matches(): void
    {
        $this->seedFixtureBuild();

        $first = $this->get('/_flow-admin/assets/studio.js');
        $first->assertStatus(200);
        $lastModified = $first->headers->get('Last-Modified');
        $this->assertNotNull($lastModified);

        $second = $this->withHeaders([
            'If-Modified-Since' => $lastModified,
        ])->get('/_flow-admin/assets/studio.js');

        $second->assertStatus(304);
    }

    public function test_studio_asset_routes_are_named_for_blade_tags(): void
    {
        $this->assertStringEndsWith('/_flow-admin/assets/studio.js', route('flow-admin.assets.studio-js'));
        $this->assertStringEndsWith('/_flow-admin/assets/studio.css', route('flow-admin.assets.studio-css'));
    }

    /**
     * Writes a minimal, deliberately fake Vite manifest + built asset pair
     * into the package's own `public/vendor/flow-admin/` directory, mimicking
     * exactly what `npm run build` produces for the `resources/js/studio.jsx`
     * entry — content-hashed filename, `file` + `css` keys — without
     * actually invoking Vite.
     */
    private function seedFixtureBuild(): void
    {
        mkdir($this->buildDir . '/.vite', recursive: true);
        mkdir($this->buildDir . '/assets', recursive: true);

        file_put_contents(
            $this->buildDir . '/assets/studio-fixture123.js',
            'window.__studioFixture = true;',
        );
        file_put_contents(
            $this->buildDir . '/assets/studio-fixture123.css',
            '.studio-fixture { display: block; }',
        );

        file_put_contents($this->buildDir . '/.vite/manifest.json', json_encode([
            'resources/js/studio.jsx' => [
                'file' => 'assets/studio-fixture123.js',
                'name' => 'studio',
                'src' => 'resources/js/studio.jsx',
                'isEntry' => true,
                'css' => ['assets/studio-fixture123.css'],
            ],
        ], JSON_THROW_ON_ERROR));
    }

    private function deleteDirectory(string $path): void
    {
        $items = scandir($path);
        $items = $items === false ? [] : $items;

        foreach ($items as $item) {
            if ($item === '.' || $item === '..') {
                continue;
            }

            $full = $path . '/' . $item;

            if (is_dir($full)) {
                $this->deleteDirectory($full);
            } else {
                unlink($full);
            }
        }

        rmdir($path);
    }

    private function extractStreamedBody(TestResponse $response): string
    {
        ob_start();
        $response->baseResponse->sendContent();

        return (string) ob_get_clean();
    }
}
