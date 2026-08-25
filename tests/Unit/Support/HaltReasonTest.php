<?php

declare(strict_types=1);

namespace Padosoft\LaravelFlowAdmin\Tests\Unit\Support;

use Padosoft\LaravelFlowAdmin\Support\HaltReason;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class HaltReasonTest extends TestCase
{
    /**
     * @return array<string, array{0: ?string, 1: ?string, 2: ?string}>
     */
    public static function reasonProvider(): array
    {
        // [errorClass, expectedLabel, expectedKind]
        return [
            // The distinction the class exists for: a person withdrew
            // permission and the loop halted. Nothing is broken.
            'revoked grant is a policy stop' => [
                'Padosoft\LaravelFlowAI\Identity\Exceptions\GrantRevokedException',
                'Delegation revoked',
                'policy',
            ],
            'tool outside the allowlist is a policy stop' => [
                'Padosoft\LaravelFlowAI\Nodes\Exceptions\AgentToolNotAllowedException',
                'Tool not allowed',
                'policy',
            ],
            'budget ceiling is a policy stop' => [
                'Padosoft\LaravelFlowAI\Nodes\Exceptions\AgentBudgetExhaustedException',
                'Budget exhausted',
                'policy',
            ],
            'guardrail denial is a policy stop' => [
                'Padosoft\LaravelFlowAI\Guardrails\PolicyDeniedException',
                'Blocked by policy',
                'policy',
            ],
            // Named, but still a failure: the operator has to look at the tool
            // server, not at the flow.
            'unreachable tool server is an error' => [
                'Padosoft\LaravelFlowAI\Mcp\Exceptions\McpConnectionException',
                'Tool server unreachable',
                'error',
            ],
            // Anything unrecognised must default to "look at this", never to
            // "a rule handled it".
            'unknown class defaults to error' => ['PDOException', 'PDOException', 'error'],
            'unknown namespaced class shows its basename' => [
                'App\Exceptions\PaymentGatewayTimeout',
                'PaymentGatewayTimeout',
                'error',
            ],
            'no class means nothing to explain' => [null, null, null],
            'empty class means nothing to explain' => ['', null, null],
        ];
    }

    #[DataProvider('reasonProvider')]
    public function test_it_classifies_a_halt_by_exception_class(?string $errorClass, ?string $label, ?string $kind): void
    {
        $this->assertSame($label, HaltReason::label($errorClass));
        $this->assertSame($kind, HaltReason::kind($errorClass));
    }

    public function test_it_matches_on_basename_so_a_subclass_keeps_its_classification(): void
    {
        // A host app subclassing the exception, or the package moving it
        // between namespaces, must not silently turn a policy stop into a
        // crash — that is the failure mode this test exists for.
        $this->assertSame('policy', HaltReason::kind('App\Support\GrantRevokedException'));
        $this->assertSame('Delegation revoked', HaltReason::label('App\Support\GrantRevokedException'));
    }

    public function test_a_trailing_separator_does_not_produce_an_empty_label(): void
    {
        $this->assertNull(HaltReason::label('App\Exceptions\\'));
        $this->assertSame('error', HaltReason::kind('App\Exceptions\\'));
    }
}
