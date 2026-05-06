<?php

declare(strict_types=1);

namespace Padosoft\LaravelFlowAdmin\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\View\View;
use Padosoft\LaravelFlowAdmin\Contracts\ReadModel;
use Padosoft\LaravelFlowAdmin\ViewModels\OutboxRow;

final class OutboxController extends Controller
{
    public function __construct(private readonly ReadModel $readModel) {}

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
}
