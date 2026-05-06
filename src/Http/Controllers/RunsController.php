<?php

declare(strict_types=1);

namespace Padosoft\LaravelFlowAdmin\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\View\View;
use Padosoft\LaravelFlowAdmin\Contracts\ReadModel;
use Padosoft\LaravelFlowAdmin\ViewModels\RunRow;

final class RunsController extends Controller
{
    public function __construct(private readonly ReadModel $readModel) {}

    public function index(Request $request): View
    {
        $status = $this->normalizedFilter($request->query('status'));
        $flow = $this->normalizedFilter($request->query('flow'));
        $query = $this->normalizedFilter($request->query('q'));
        $page = max(1, (int) $request->query('page', 1));
        $perPage = 25;

        $result = $this->readModel->listRuns(
            status: $status,
            flow: $flow,
            query: $query,
            page: $page,
            perPage: $perPage,
        );

        $items = array_map(RunRow::fromDto(...), $result->items);
        $definitions = $this->readModel->definitions();

        $statusCounts = [
            'all' => $this->readModel->listRuns(perPage: 1)->total,
            'running' => $this->readModel->listRuns(status: 'running', perPage: 1)->total,
            'paused' => $this->readModel->listRuns(status: 'paused', perPage: 1)->total,
            'failed' => $this->readModel->listRuns(status: 'failed', perPage: 1)->total,
            'success' => $this->readModel->listRuns(status: 'success', perPage: 1)->total,
            'compensated' => $this->readModel->listRuns(status: 'compensated', perPage: 1)->total,
            'pending' => $this->readModel->listRuns(status: 'pending', perPage: 1)->total,
        ];

        return view('flow-admin::pages.runs', [
            'route' => 'runs',
            'pageTitle' => 'Runs',
            'breadcrumbs' => [
                ['label' => 'Runs'],
            ],
            'counts' => [
                'running' => $statusCounts['running'],
                'approvals' => $this->readModel->listApprovals(status: 'pending', perPage: 1)->total,
                'outbox' => $this->readModel->listWebhookOutbox(status: 'pending', perPage: 1)->total,
            ],
            'items' => $items,
            'definitions' => $definitions,
            'statusCounts' => $statusCounts,
            'filters' => [
                'status' => $status,
                'flow' => $flow,
                'q' => $query,
            ],
            'pagination' => [
                'page' => $result->page,
                'perPage' => $result->perPage,
                'total' => $result->total,
                'pages' => $result->totalPages(),
            ],
        ]);
    }

    private function normalizedFilter(mixed $value): ?string
    {
        $filter = trim((string) $value);

        return $filter === '' || $filter === 'all' ? null : $filter;
    }
}
