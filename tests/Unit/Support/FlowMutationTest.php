<?php

declare(strict_types=1);

namespace Padosoft\LaravelFlowAdmin\Tests\Unit\Support;

use Padosoft\LaravelFlow\Exceptions\ApprovalPersistenceException;
use Padosoft\LaravelFlow\Exceptions\FlowExecutionException;
use Padosoft\LaravelFlow\Exceptions\FlowInputException;
use Padosoft\LaravelFlowAdmin\Support\FlowMutation;
use Padosoft\LaravelFlowAdmin\Tests\TestCase;
use RuntimeException;

/**
 * Pins the exception → HTTP-status mapping every mutation controller relies
 * on, and the uniform {success, message, data} envelope. Boots the app
 * because FlowMutation uses the `response()` helper.
 */
final class FlowMutationTest extends TestCase
{
    public function test_a_string_result_becomes_a_success_envelope(): void
    {
        $response = FlowMutation::run(fn (): string => 'Done.');

        $this->assertSame(200, $response->getStatusCode());
        $this->assertSame(
            ['success' => true, 'message' => 'Done.', 'data' => []],
            $response->getData(true),
        );
    }

    public function test_an_array_result_carries_message_and_data_with_a_custom_status(): void
    {
        $response = FlowMutation::run(
            fn (): array => ['message' => 'Created.', 'data' => ['runId' => 'r_9']],
            201,
        );

        $this->assertSame(201, $response->getStatusCode());
        $this->assertSame(
            ['success' => true, 'message' => 'Created.', 'data' => ['runId' => 'r_9']],
            $response->getData(true),
        );
    }

    public function test_flow_input_exception_maps_to_422_and_surfaces_its_message(): void
    {
        $response = FlowMutation::run(function (): string {
            throw new FlowInputException('Approval token hash must not be blank.');
        });

        $this->assertSame(422, $response->getStatusCode());
        $this->assertFalse($response->getData(true)['success']);
        $this->assertSame('Approval token hash must not be blank.', $response->getData(true)['message']);
    }

    public function test_flow_execution_exception_maps_to_409_and_surfaces_its_curated_message(): void
    {
        $response = FlowMutation::run(function (): string {
            throw new FlowExecutionException('Flow run [r_1] is not terminal and cannot be replayed.');
        });

        $this->assertSame(409, $response->getStatusCode());
        $this->assertSame('Flow run [r_1] is not terminal and cannot be replayed.', $response->getData(true)['message']);
    }

    public function test_approval_persistence_exception_maps_to_503_without_leaking_its_message(): void
    {
        $response = FlowMutation::run(function (): string {
            throw new ApprovalPersistenceException('SQLSTATE[HY000] no such table: flow_approvals');
        });

        $this->assertSame(503, $response->getStatusCode());
        // Generic message — the raw persistence message can carry DB internals.
        $this->assertSame('The approval store is unavailable. Try again later.', $response->getData(true)['message']);
    }

    public function test_an_unexpected_throwable_maps_to_a_sanitized_500(): void
    {
        $response = FlowMutation::run(function (): string {
            throw new RuntimeException('boom with internal detail');
        });

        $this->assertSame(500, $response->getStatusCode());
        $this->assertSame('Something went wrong. Try again.', $response->getData(true)['message']);
    }
}
