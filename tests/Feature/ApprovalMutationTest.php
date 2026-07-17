<?php

declare(strict_types=1);

namespace Padosoft\LaravelFlowAdmin\Tests\Feature;

use Illuminate\Support\Facades\DB;
use Padosoft\LaravelFlow\ApprovalTokenManager;
use Padosoft\LaravelFlow\FlowRun;

/**
 * E-PR6: the approvals page approve/reject actions decide a paused run's
 * approval by its token HASH, through `Flow::resumeByHash()`/`rejectByHash()`.
 * Deny-by-default is mandatory; the allow path drives a genuinely-paused
 * engine run to prove the wiring end-to-end.
 */
final class ApprovalMutationTest extends MutationTestCase
{
    private const A_HASH = 'a1b2c3d4e5f6a7b8c9d0e1f2a3b4c5d6e7f8a9b0c1d2e3f4a5b6c7d8e9f0a1b2';

    public function test_approve_is_forbidden_by_default(): void
    {
        $response = $this->postJson(route('flow-admin.approvals.approve', ['tokenHash' => self::A_HASH]));

        $response->assertStatus(403);
    }

    public function test_reject_is_forbidden_by_default(): void
    {
        $response = $this->postJson(route('flow-admin.approvals.reject', ['tokenHash' => self::A_HASH]));

        $response->assertStatus(403);
    }

    public function test_approve_by_hash_resumes_the_paused_run(): void
    {
        $engine = $this->bootFlowPersistence();
        $this->allowAllActions();
        ['run' => $run, 'plainToken' => $plain] = $this->seedPausedApprovalRun($engine);
        $this->assertSame(FlowRun::STATUS_PAUSED, $run->status);

        $response = $this->postJson(route('flow-admin.approvals.approve', [
            'tokenHash' => ApprovalTokenManager::hashToken($plain),
        ]));

        $response->assertStatus(200);
        $response->assertJsonPath('success', true);
        $this->assertSame(
            FlowRun::STATUS_SUCCEEDED,
            DB::table('flow_runs')->where('id', $run->id)->value('status'),
        );
    }

    public function test_reject_by_hash_fails_the_paused_run(): void
    {
        $engine = $this->bootFlowPersistence();
        $this->allowAllActions();
        ['run' => $run, 'plainToken' => $plain] = $this->seedPausedApprovalRun($engine);

        $response = $this->postJson(route('flow-admin.approvals.reject', [
            'tokenHash' => ApprovalTokenManager::hashToken($plain),
        ]));

        $response->assertStatus(200);
        $response->assertJsonPath('success', true);
        $this->assertSame(
            FlowRun::STATUS_FAILED,
            DB::table('flow_runs')->where('id', $run->id)->value('status'),
        );
    }

    public function test_approve_with_an_unknown_hash_returns_409(): void
    {
        $this->bootFlowPersistence();
        $this->allowAllActions();

        $response = $this->postJson(route('flow-admin.approvals.approve', [
            'tokenHash' => ApprovalTokenManager::hashToken('never-issued'),
        ]));

        $response->assertStatus(409);
        $response->assertJsonPath('success', false);
    }

    public function test_reversing_an_already_decided_approval_returns_409(): void
    {
        $engine = $this->bootFlowPersistence();
        $this->allowAllActions();
        ['run' => $run, 'plainToken' => $plain] = $this->seedPausedApprovalRun($engine);
        $hash = ApprovalTokenManager::hashToken($plain);

        // First decision grants and resumes the run to success.
        $this->postJson(route('flow-admin.approvals.approve', ['tokenHash' => $hash]))->assertStatus(200);

        // A second, OPPOSITE decision on the now-decided token is a conflict.
        $response = $this->postJson(route('flow-admin.approvals.reject', ['tokenHash' => $hash]));

        $response->assertStatus(409);
        $response->assertJsonPath('success', false);
        // The run stays succeeded — the reversal never took effect.
        $this->assertSame(
            FlowRun::STATUS_SUCCEEDED,
            DB::table('flow_runs')->where('id', $run->id)->value('status'),
        );
    }
}
