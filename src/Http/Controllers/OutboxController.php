<?php

declare(strict_types=1);

namespace Padosoft\LaravelFlowAdmin\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\View\View;
use Padosoft\LaravelFlow\Exceptions\FlowExecutionException;
use Padosoft\LaravelFlow\FlowEngine;
use Padosoft\LaravelFlowAdmin\Contracts\ReadModel;
use Padosoft\LaravelFlowAdmin\Support\Authorize;
use Padosoft\LaravelFlowAdmin\Support\FlowMutation;
use Padosoft\LaravelFlowAdmin\ViewModels\OutboxRow;

final class OutboxController extends Controller
{
    public function __construct(
        private readonly ReadModel $readModel,
        private readonly FlowEngine $engine,
    ) {}

    public function index(Request $request): View
    {
        $status = trim((string) $request->query('status', ''));
        $status = $status === '' || $status === 'all' ? null : $status;

        $query = trim((string) $request->query('q', ''));
        $query = $query === '' ? null : $query;

        $page = max(1, (int) $request->query('page', 1));
        $result = $this->readModel->listWebhookOutbox(status: $status, query: $query, page: $page, perPage: 25);

        return view('flow-admin::pages.outbox', [
            'route' => 'outbox',
            'pageTitle' => 'Outbox',
            'breadcrumbs' => [['label' => 'Outbox']],
            'counts' => [
                'running' => $this->readModel->listRuns(status: 'running', perPage: 1)->total,
                'approvals' => $this->readModel->listApprovals(status: 'pending', perPage: 1)->total,
                'outbox' => $this->readModel->listWebhookOutbox(status: 'pending', perPage: 1)->total,
            ],
            'filters' => ['status' => $status, 'q' => $query],
            'items' => array_map(OutboxRow::fromDto(...), $result->items),
            'pendingCount' => $this->readModel->listWebhookOutbox(status: 'pending', perPage: 1)->total,
            'deliveredCount' => $this->readModel->listWebhookOutbox(status: 'delivered', perPage: 1)->total,
            'failedCount' => $this->readModel->listWebhookOutbox(status: 'failed', perPage: 1)->total,
            'pagination' => [
                'page' => $result->page,
                'perPage' => $result->perPage,
                'total' => $result->total,
                'pages' => $result->totalPages(),
            ],
        ]);
    }

    /**
     * Requeues a FAILED webhook outbox row for another delivery attempt.
     * `Flow::redeliverWebhook()` resets a `failed` row to `pending` (attempts
     * back to 0) and returns false for any other state (unknown id, already
     * delivered, still pending/in-flight) — translated here into a 409 so the
     * operator sees why nothing happened. The outbox id is a string in the
     * read model; the seam and authorizer both key on int, so it is cast at
     * this boundary. Gated by `ActionAuthorizer::canRetryWebhook()`.
     */
    public function redeliver(string $id): JsonResponse
    {
        $outboxId = (int) $id;

        // Guard the int cast: reject any param that doesn't round-trip back to
        // the exact URL segment (a leading-zero form, or — belt-and-suspenders
        // with the route's length cap — an overflowing digit string), so the
        // authorizer and engine can never act on a different row than the URL
        // names. A malformed id is a client error, resolved before authorizing.
        if ((string) $outboxId !== $id || $outboxId < 1) {
            return response()->json([
                'success' => false,
                'message' => 'Invalid webhook id.',
                'data' => [],
            ], 404);
        }

        return Authorize::action(
            'retry_webhook',
            fn (): JsonResponse => FlowMutation::run(function () use ($outboxId): string {
                if (! $this->engine->redeliverWebhook($outboxId)) {
                    // No `failed` row matched (unknown id / already delivered /
                    // still pending / in-flight). Signal a 409 through the same
                    // typed-exception mapping every other mutation uses.
                    throw new FlowExecutionException(
                        'This webhook is not in a failed state and cannot be redelivered.',
                    );
                }

                return 'Webhook queued for redelivery.';
            }),
            context: ['outboxId' => $outboxId],
        );
    }
}
