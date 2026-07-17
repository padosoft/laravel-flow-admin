<?php

declare(strict_types=1);

namespace Padosoft\LaravelFlowAdmin\Tests\Feature;

use Padosoft\LaravelFlow\FlowEngine;
use Padosoft\LaravelFlow\FlowRun;
use Padosoft\LaravelFlowAdmin\Authorizers\AllowAllAuthorizer;
use Padosoft\LaravelFlowAdmin\Contracts\ActionAuthorizer;
use Padosoft\LaravelFlowAdmin\Tests\Concerns\MigratesFlowTables;
use Padosoft\LaravelFlowAdmin\Tests\Stubs\AlwaysSucceedsHandler;
use Padosoft\LaravelFlowAdmin\Tests\TestCase;

/**
 * Base for the E-PR6 mutation Feature tests. Boots a real core-persisted
 * SQLite database (so `FlowEngine::cancel()/replay()/resumeByHash()/
 * redeliverWebhook()` operate on genuine engine state) and centralizes the
 * allow/deny authorizer swap and the paused-approval-run seed.
 *
 * The DEFAULT `ActionAuthorizer` binding stays `DenyAllAuthorizer`, so any
 * test that does not call {@see allowAllActions()} exercises the
 * deny-by-default posture every mutation route must honour.
 */
abstract class MutationTestCase extends TestCase
{
    use MigratesFlowTables;

    protected function tearDown(): void
    {
        $this->tearDownFlowDatabase();

        parent::tearDown();
    }

    /**
     * Migrate the core tables and turn on persistence + a real lock store, so
     * the engine actually persists runs/approvals/outbox rows and the
     * approval-decision lock (keyed by run id) can be acquired. Returns the
     * freshly-built engine singleton the HTTP controller will also resolve.
     */
    protected function bootFlowPersistence(): FlowEngine
    {
        $this->setUpFlowDatabase();

        $this->app['config']->set('laravel-flow.persistence.enabled', true);
        // resumeByHash()/rejectByHash() acquire a decision lock through this
        // cache store; the engine rejects the array/no-lock store, so use the
        // file store (matches core's own ApproveByHashTest).
        $this->app['config']->set('laravel-flow.queue.lock_store', 'file');
        $this->app->forgetInstance(FlowEngine::class);

        /** @var FlowEngine $engine */
        $engine = $this->app->make(FlowEngine::class);

        return $engine;
    }

    protected function allowAllActions(): void
    {
        $this->app->bind(ActionAuthorizer::class, AllowAllAuthorizer::class);
        $this->app->forgetInstance(ActionAuthorizer::class);
    }

    /**
     * Runs a `step → approvalGate → step` flow so it pauses at the gate, and
     * returns the paused run plus the gate's plaintext approval token (whose
     * SHA-256 hash the dashboard routes carry).
     *
     * @return array{run: FlowRun, plainToken: string}
     */
    protected function seedPausedApprovalRun(FlowEngine $engine, string $flowName = 'flow.mutation.approval'): array
    {
        $engine->define($flowName)
            ->step('create', AlwaysSucceedsHandler::class)
            ->approvalGate('manager')
            ->step('publish', AlwaysSucceedsHandler::class)
            ->register();

        $run = $engine->execute($flowName, []);

        return [
            'run' => $run,
            'plainToken' => $run->approvalTokens['manager']->plainTextToken,
        ];
    }
}
