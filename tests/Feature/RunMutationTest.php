<?php

declare(strict_types=1);

namespace Padosoft\LaravelFlowAdmin\Tests\Feature;

use Illuminate\Support\Facades\DB;
use Padosoft\LaravelFlow\FlowRun;

/**
 * E-PR6: the run-detail Cancel/Replay actions drive `Flow::cancel()` and
 * `Flow::replay()`. Deny-by-default is mandatory; the allow path exercises
 * the seam against a real engine run and asserts the exception→HTTP mapping
 * (a non-terminal replay / unknown run → 409).
 */
final class RunMutationTest extends MutationTestCase
{
    public function test_cancel_is_forbidden_by_default(): void
    {
        $response = $this->postJson(route('flow-admin.runs.cancel', ['id' => 'run-x']));

        $response->assertStatus(403);
    }

    public function test_replay_is_forbidden_by_default(): void
    {
        $response = $this->postJson(route('flow-admin.runs.replay', ['id' => 'run-x']));

        $response->assertStatus(403);
    }

    public function test_cancel_aborts_an_active_run(): void
    {
        $engine = $this->bootFlowPersistence();
        $this->allowAllActions();
        ['run' => $run] = $this->seedPausedApprovalRun($engine);
        $this->assertSame(FlowRun::STATUS_PAUSED, $run->status);

        $response = $this->postJson(route('flow-admin.runs.cancel', ['id' => $run->id]));

        $response->assertStatus(200);
        $response->assertJsonPath('success', true);
        $this->assertSame(
            FlowRun::STATUS_ABORTED,
            DB::table('flow_runs')->where('id', $run->id)->value('status'),
        );
    }

    public function test_replay_of_a_non_terminal_run_returns_409(): void
    {
        $engine = $this->bootFlowPersistence();
        $this->allowAllActions();
        ['run' => $run] = $this->seedPausedApprovalRun($engine);

        // A paused run is not terminal; replay() rejects it → mapped to 409.
        $response = $this->postJson(route('flow-admin.runs.replay', ['id' => $run->id]));

        $response->assertStatus(409);
        $response->assertJsonPath('success', false);
    }

    public function test_cancel_of_an_unknown_run_returns_409(): void
    {
        $this->bootFlowPersistence();
        $this->allowAllActions();

        $response = $this->postJson(route('flow-admin.runs.cancel', ['id' => 'run-does-not-exist']));

        $response->assertStatus(409);
        $response->assertJsonPath('success', false);
    }

    public function test_cancelling_an_already_terminal_run_is_idempotent(): void
    {
        $engine = $this->bootFlowPersistence();
        $this->allowAllActions();
        ['run' => $run] = $this->seedPausedApprovalRun($engine);

        // First cancel aborts the active run.
        $this->postJson(route('flow-admin.runs.cancel', ['id' => $run->id]))->assertStatus(200);

        // A second cancel on the now-terminal run is a no-op success (core's
        // cancel() is idempotent on an already-terminal run), NOT a 409.
        $response = $this->postJson(route('flow-admin.runs.cancel', ['id' => $run->id]));

        $response->assertStatus(200);
        $response->assertJsonPath('success', true);
        $this->assertSame(
            FlowRun::STATUS_ABORTED,
            DB::table('flow_runs')->where('id', $run->id)->value('status'),
        );
    }
}
