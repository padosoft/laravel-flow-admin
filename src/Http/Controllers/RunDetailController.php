<?php

declare(strict_types=1);

namespace Padosoft\LaravelFlowAdmin\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Routing\Controller;
use Illuminate\View\View;
use Padosoft\LaravelFlow\FlowEngine;
use Padosoft\LaravelFlowAdmin\Contracts\ReadModel;
use Padosoft\LaravelFlowAdmin\Support\Authorize;
use Padosoft\LaravelFlowAdmin\Support\FlowMutation;
use Padosoft\LaravelFlowAdmin\ViewModels\RunDetailViewModel;

final class RunDetailController extends Controller
{
    public function __construct(
        private readonly ReadModel $readModel,
        private readonly FlowEngine $engine,
    ) {}

    public function show(string $id): View
    {
        $detail = $this->readModel->findRun($id);
        abort_if($detail === null, 404);

        return view('flow-admin::pages.run-detail', [
            'route' => 'runs',
            'pageTitle' => 'Run Detail',
            'breadcrumbs' => [
                ['label' => 'Runs', 'url' => route('flow-admin.runs.index')],
                ['label' => $id, 'mono' => true],
            ],
            'viewModel' => RunDetailViewModel::fromDto($detail),
            'counts' => [
                'running' => $this->readModel->listRuns(status: 'running', perPage: 1)->total,
                'approvals' => $this->readModel->listApprovals(status: 'pending', perPage: 1)->total,
                'outbox' => $this->readModel->listWebhookOutbox(status: 'pending', perPage: 1)->total,
            ],
        ]);
    }

    /**
     * Cancels an active run: `Flow::cancel()` aborts the run and terminates
     * its non-terminal nodes (pending→skipped, running/paused→failed). It is
     * idempotent on an already-terminal run. Gated by
     * `ActionAuthorizer::canCancelRun()`.
     */
    public function cancel(string $id): JsonResponse
    {
        return Authorize::action(
            'cancel_run',
            fn (): JsonResponse => FlowMutation::run(function () use ($id): array {
                $run = $this->engine->cancel($id);

                return ['message' => 'Run cancelled.', 'data' => ['status' => $run->status]];
            }),
            context: ['runId' => $id],
        );
    }

    /**
     * Replays a terminal, pinned graph run: `Flow::replay()` re-executes the
     * exact stored definition version as a NEW linked run. Rejects a
     * non-terminal or unpinned run with a 409. Gated by
     * `ActionAuthorizer::canReplayRun()`.
     */
    public function replay(string $id): JsonResponse
    {
        return Authorize::action(
            'replay_run',
            fn (): JsonResponse => FlowMutation::run(function () use ($id): array {
                $replay = $this->engine->replay($id);

                return ['message' => 'Run replayed as a new run.', 'data' => ['runId' => $replay->id]];
            }),
            context: ['runId' => $id],
        );
    }
}
