<?php

declare(strict_types=1);

namespace Padosoft\LaravelFlowAdmin\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\View\View;
use Padosoft\LaravelFlow\FlowEngine;
use Padosoft\LaravelFlowAdmin\Contracts\ReadModel;
use Padosoft\LaravelFlowAdmin\Support\Authorize;
use Padosoft\LaravelFlowAdmin\Support\FlowMutation;
use Padosoft\LaravelFlowAdmin\ViewModels\ApprovalCard;

final class ApprovalsController extends Controller
{
    public function __construct(
        private readonly ReadModel $readModel,
        private readonly FlowEngine $engine,
    ) {}

    public function index(Request $request): View
    {
        $status = trim((string) $request->query('status', 'pending'));
        $status = $status === '' || $status === 'all' ? null : $status;

        $query = trim((string) $request->query('q', ''));
        $query = $query === '' ? null : $query;

        $page = max(1, (int) $request->query('page', 1));
        $result = $this->readModel->listApprovals(status: $status, query: $query, page: $page, perPage: 25);

        return view('flow-admin::pages.approvals', [
            'route' => 'approvals',
            'pageTitle' => 'Approvals',
            'breadcrumbs' => [['label' => 'Approvals']],
            'counts' => [
                'running' => $this->readModel->listRuns(status: 'running', perPage: 1)->total,
                'approvals' => $this->readModel->listApprovals(status: 'pending', perPage: 1)->total,
                'outbox' => $this->readModel->listWebhookOutbox(status: 'pending', perPage: 1)->total,
            ],
            'filters' => ['status' => $status, 'q' => $query],
            'items' => array_map(ApprovalCard::fromDto(...), $result->items),
            'pagination' => [
                'page' => $result->page,
                'perPage' => $result->perPage,
                'total' => $result->total,
                'pages' => $result->totalPages(),
            ],
        ]);
    }

    /**
     * Grants a pending approval by its token HASH, resuming the paused run.
     * The route carries the SHA-256 hash (not the plaintext token, which the
     * dashboard never holds); `Flow::resumeByHash()` decides the approval
     * exactly as `resume()` would by the plaintext token. Gated by
     * `ActionAuthorizer::canApproveByToken()`.
     */
    public function approve(string $tokenHash): JsonResponse
    {
        return Authorize::action(
            'approve',
            fn (): JsonResponse => FlowMutation::run(function () use ($tokenHash): string {
                $this->engine->resumeByHash($tokenHash);

                return 'Approval granted. The run has resumed.';
            }),
            context: ['tokenHash' => $tokenHash],
        );
    }

    /**
     * Rejects a pending approval by its token HASH, failing the paused run.
     * Gated by `ActionAuthorizer::canRejectByToken()`.
     */
    public function reject(string $tokenHash): JsonResponse
    {
        return Authorize::action(
            'reject',
            fn (): JsonResponse => FlowMutation::run(function () use ($tokenHash): string {
                $this->engine->rejectByHash($tokenHash);

                return 'Approval rejected. The run has been failed.';
            }),
            context: ['tokenHash' => $tokenHash],
        );
    }
}
