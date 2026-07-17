<?php

declare(strict_types=1);

namespace Padosoft\LaravelFlowAdmin\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Routing\Controller;
use Illuminate\View\View;
use Padosoft\LaravelFlowAdmin\Contracts\Dto\RunDetail;
use Padosoft\LaravelFlowAdmin\Contracts\Dto\Step;
use Padosoft\LaravelFlowAdmin\Contracts\ReadModel;

/**
 * Live run monitor. The page is a React island that either subscribes to
 * core's per-run private broadcast channel (`{prefix}.run.{runId}`, events
 * `node.transitioned` / `run.progress`) when broadcasting is enabled and a
 * `window.Echo` is present, or falls back to polling {@see self::state()}.
 *
 * `state()` returns ONLY node lifecycle state (id, state, cache-hit flag,
 * order) and aggregate progress — never the run's input/output payloads or
 * audit trail (unlike the full run-detail page), so the frequently-polled
 * endpoint has a minimal, payload-free surface.
 */
final class RunMonitorController extends Controller
{
    public function __construct(private readonly ReadModel $readModel) {}

    public function show(string $id): View
    {
        $detail = $this->readModel->findRun($id);
        abort_if($detail === null, 404);

        return view('flow-admin::pages.run-monitor', [
            'route' => 'runs',
            'pageTitle' => 'Live monitor',
            'breadcrumbs' => [
                ['label' => 'Runs', 'url' => route('flow-admin.runs.index')],
                ['label' => $id, 'url' => route('flow-admin.runs.show', ['id' => $id]), 'mono' => true],
                ['label' => 'Monitor'],
            ],
            'counts' => [
                'running' => $this->readModel->listRuns(status: 'running', perPage: 1)->total,
                'approvals' => $this->readModel->listApprovals(status: 'pending', perPage: 1)->total,
                'outbox' => $this->readModel->listWebhookOutbox(status: 'pending', perPage: 1)->total,
            ],
            'runId' => $id,
            'broadcasting' => (bool) config('laravel-flow.broadcasting.enabled', false),
            'channel' => $this->channelFor($id),
        ]);
    }

    public function state(string $id): JsonResponse
    {
        $detail = $this->readModel->findRun($id);

        if ($detail === null) {
            return response()->json(['message' => "Run [{$id}] not found."], 404);
        }

        return response()->json($this->statePayload($detail));
    }

    /**
     * @return array<string, mixed>
     */
    private function statePayload(RunDetail $detail): array
    {
        $nodes = array_map(
            fn (Step $step, int $index): array => [
                'node_id' => $step->name,
                'state' => $this->normalizeState($step->status),
                'cache_hit' => $step->cacheHit,
                'sequence' => $index + 1,
            ],
            $detail->steps,
            array_keys($detail->steps),
        );

        $total = count($nodes);
        $completed = count(array_filter($nodes, static fn (array $n): bool => $n['state'] === 'succeeded'));
        $failed = count(array_filter($nodes, static fn (array $n): bool => $n['state'] === 'failed'));

        return [
            'run_id' => $detail->summary->id,
            'status' => $detail->summary->status,
            'progress' => [
                'total' => $total,
                'completed' => $completed,
                'failed' => $failed,
                // Settled = completed OR failed, matching core's own
                // GraphRunProgressUpdated::progressPct() so the polled/live
                // recomputed value never disagrees with a `run.progress`
                // broadcast on a run that has failures.
                'pct' => $total > 0 ? (int) round(($completed + $failed) / $total * 100) : 0,
            ],
            'nodes' => $nodes,
        ];
    }

    /**
     * The Eloquent adapter passes core's real `NodeState` vocabulary through
     * (`succeeded`, …); the array/demo adapter's fixtures use the legacy
     * `success` slug. Canonicalize to the core `NodeState` value the monitor
     * colors and counts by, so both adapters agree on one vocabulary.
     */
    private function normalizeState(string $status): string
    {
        return $status === 'success' ? 'succeeded' : $status;
    }

    private function channelFor(string $runId): string
    {
        $prefix = (string) config('laravel-flow.broadcasting.channel_prefix', 'laravel-flow');

        return "{$prefix}.run.{$runId}";
    }
}
