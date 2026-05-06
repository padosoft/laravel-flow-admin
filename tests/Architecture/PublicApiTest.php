<?php

declare(strict_types=1);

namespace Padosoft\LaravelFlowAdmin\Tests\Architecture;

use Padosoft\LaravelFlowAdmin\FlowAdminServiceProvider;
use Padosoft\LaravelFlowAdmin\Http\Controllers\OverviewController;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class PublicApiTest extends TestCase
{
    public function test_service_provider_exists(): void
    {
        $this->assertTrue(
            class_exists(FlowAdminServiceProvider::class),
            'FlowAdminServiceProvider must exist'
        );
    }

    public function test_config_file_exists(): void
    {
        $this->assertFileExists(
            __DIR__ . '/../../config/flow-admin.php',
            'config/flow-admin.php must exist'
        );
    }

    public function test_routes_file_exists(): void
    {
        $this->assertFileExists(
            __DIR__ . '/../../routes/flow-admin.php',
            'routes/flow-admin.php must exist'
        );
    }

    public function test_overview_view_file_exists(): void
    {
        $this->assertFileExists(
            __DIR__ . '/../../resources/views/pages/overview.blade.php',
            'resources/views/pages/overview.blade.php must exist'
        );
    }

    public function test_overview_controller_exists(): void
    {
        $this->assertTrue(
            class_exists(OverviewController::class),
            'OverviewController must exist'
        );
    }

    #[DataProvider('internalNamespaceProvider')]
    public function test_no_internal_laravel_flow_namespaces_referenced(string $namespace): void
    {
        $srcDir = __DIR__ . '/../../src';
        $files = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($srcDir));

        foreach ($files as $file) {
            if ($file->getExtension() !== 'php') {
                continue;
            }
            $content = file_get_contents($file->getPathname());
            $this->assertStringNotContainsString(
                $namespace,
                $content,
                "File {$file->getFilename()} must not reference @internal namespace {$namespace}"
            );
        }
    }

    public static function internalNamespaceProvider(): array
    {
        return [
            ['Padosoft\\LaravelFlow\\Persistence'],
            ['Padosoft\\LaravelFlow\\Models'],
            ['Padosoft\\LaravelFlow\\Queue'],
            ['Padosoft\\LaravelFlow\\Jobs'],
            ['Padosoft\\LaravelFlow\\Console'],
        ];
    }
}
