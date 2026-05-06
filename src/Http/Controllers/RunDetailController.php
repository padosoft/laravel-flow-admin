<?php

declare(strict_types=1);

namespace Padosoft\LaravelFlowAdmin\Http\Controllers;

use Illuminate\Routing\Controller;
use Illuminate\View\View;
use Padosoft\LaravelFlowAdmin\Contracts\ReadModel;
use Padosoft\LaravelFlowAdmin\ViewModels\RunDetailViewModel;

final class RunDetailController extends Controller
{
    public function __construct(private readonly ReadModel $readModel) {}

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
}
