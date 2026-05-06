<?php

declare(strict_types=1);

namespace Padosoft\LaravelFlowAdmin\Http\Controllers;

use Illuminate\Routing\Controller;
use Illuminate\View\View;
use Padosoft\LaravelFlowAdmin\Contracts\ReadModel;

final class SettingsController extends Controller
{
    public function __construct(private readonly ReadModel $readModel) {}

    public function index(): View
    {
        $flowAdmin = (array) config('flow-admin', []);

        return view('flow-admin::pages.settings', [
            'route' => 'settings',
            'pageTitle' => 'Settings',
            'breadcrumbs' => [['label' => 'Settings']],
            'counts' => [
                'running' => $this->readModel->listRuns(status: 'running', perPage: 1)->total,
                'approvals' => $this->readModel->listApprovals(status: 'pending', perPage: 1)->total,
                'outbox' => $this->readModel->listWebhookOutbox(status: 'pending', perPage: 1)->total,
            ],
            'flowAdmin' => $flowAdmin,
            'laravelFlow' => (array) config('laravel-flow', []),
        ]);
    }
}
