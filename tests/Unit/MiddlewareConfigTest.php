<?php

declare(strict_types=1);

namespace Padosoft\LaravelFlowAdmin\Tests\Unit;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * Pin: an empty / whitespace-only / comma-only FLOW_ADMIN_MIDDLEWARE must
 * NEVER collapse to []. Falling back to ['web'] preserves session/CSRF and
 * avoids a silent security regression where an operator who sets
 * FLOW_ADMIN_MIDDLEWARE="" expecting to drop only `auth` actually drops the
 * full web stack.
 *
 * Reviewed by Copilot on PR #10 (2026-05-06): config/flow-admin.php:25.
 */
final class MiddlewareConfigTest extends TestCase
{
    /**
     * @return array<string, array{0: string|null, 1: array<int, string>}>
     */
    public static function envValueProvider(): array
    {
        return [
            'unset → web,auth default' => [null,            ['web', 'auth']],
            'explicit web,auth' => ['web,auth',      ['web', 'auth']],
            'explicit web only' => ['web',           ['web']],
            'web,auth,verified' => ['web,auth,verified', ['web', 'auth', 'verified']],
            'whitespace tolerated' => [' web , auth ',  ['web', 'auth']],
            'empty string falls back to web' => ['',              ['web']],
            'whitespace-only falls back' => ['   ',           ['web']],
            'commas-only falls back' => [',,,',           ['web']],
            'trailing comma normalised' => ['web,auth,',     ['web', 'auth']],
            'leading comma normalised' => [',web,auth',     ['web', 'auth']],
        ];
    }

    #[DataProvider('envValueProvider')]
    public function test_middleware_resolution(?string $envValue, array $expected): void
    {
        if ($envValue === null) {
            unset($_ENV['FLOW_ADMIN_MIDDLEWARE'], $_SERVER['FLOW_ADMIN_MIDDLEWARE']);
            putenv('FLOW_ADMIN_MIDDLEWARE');
        } else {
            $_ENV['FLOW_ADMIN_MIDDLEWARE'] = $envValue;
            $_SERVER['FLOW_ADMIN_MIDDLEWARE'] = $envValue;
            putenv("FLOW_ADMIN_MIDDLEWARE={$envValue}");
        }

        $config = require __DIR__ . '/../../config/flow-admin.php';

        $this->assertSame($expected, $config['middleware']);

        // Cleanup so other tests don't see leftover state.
        unset($_ENV['FLOW_ADMIN_MIDDLEWARE'], $_SERVER['FLOW_ADMIN_MIDDLEWARE']);
        putenv('FLOW_ADMIN_MIDDLEWARE');
    }
}
