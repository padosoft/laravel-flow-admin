<?php

declare(strict_types=1);

namespace Padosoft\LaravelFlowAdmin\Tests\Unit\ViewModels;

use Padosoft\LaravelFlowAdmin\Contracts\Dto\OutboxEntry;
use Padosoft\LaravelFlowAdmin\ViewModels\OutboxRow;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class OutboxRowTest extends TestCase
{
    /**
     * @return array<string, array{0: string, 1: bool}>
     */
    public static function retryEligibilityProvider(): array
    {
        return [
            'pending can retry' => ['pending', true],
            'failed can retry' => ['failed', true],
            'dead can retry' => ['dead', true],
            'delivered cannot' => ['delivered', false],
            'unknown cannot' => ['some-future-status', false],
        ];
    }

    #[DataProvider('retryEligibilityProvider')]
    public function test_can_retry_matches_status(string $status, bool $expected): void
    {
        $dto = new OutboxEntry(
            id: 'o_1',
            eventType: 'run.succeeded',
            destination: 'https://hooks.example.test/wh',
            status: $status,
            attempts: 1,
            nextAttemptAt: null,
            lastError: null,
        );

        $this->assertSame($expected, OutboxRow::fromDto($dto)->canRetry);
    }

    public function test_status_label_uses_format_helper(): void
    {
        $dto = new OutboxEntry(
            id: 'o_2',
            eventType: 'approval.requested',
            destination: 'queue:webhooks',
            status: 'dead',
            attempts: 5,
            nextAttemptAt: null,
            lastError: 'HTTP 410 Gone (final)',
        );

        $vm = OutboxRow::fromDto($dto);

        $this->assertSame('Dead-letter', $vm->statusLabel);
        $this->assertSame('HTTP 410 Gone (final)', $vm->lastError);
        $this->assertSame(5, $vm->attempts);
    }
}
