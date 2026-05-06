<?php

declare(strict_types=1);

namespace Padosoft\LaravelFlowAdmin\Http\Controllers;

use Illuminate\Routing\Controller;
use Illuminate\View\View;
use Padosoft\LaravelFlowAdmin\Contracts\ReadModel;
use Padosoft\LaravelFlowAdmin\ViewModels\DefinitionRow;

final class DefinitionsController extends Controller
{
    public function __construct(private readonly ReadModel $readModel) {}

    public function index(): View
    {
        return view('flow-admin::pages.definitions', [
            'route' => 'definitions',
            'pageTitle' => 'Definitions',
            'breadcrumbs' => [['label' => 'Definitions']],
            'counts' => [
                'running' => $this->readModel->listRuns(status: 'running', perPage: 1)->total,
                'approvals' => $this->readModel->listApprovals(status: 'pending', perPage: 1)->total,
                'outbox' => $this->readModel->listWebhookOutbox(status: 'pending', perPage: 1)->total,
            ],
            'definitions' => array_map(DefinitionRow::fromDto(...), $this->readModel->definitions()),
        ]);
    }
}
