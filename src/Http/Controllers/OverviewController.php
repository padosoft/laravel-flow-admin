<?php

declare(strict_types=1);

namespace Padosoft\LaravelFlowAdmin\Http\Controllers;

use Illuminate\Routing\Controller;
use Illuminate\View\View;
use Padosoft\LaravelFlowAdmin\Contracts\ReadModel;
use Padosoft\LaravelFlowAdmin\ViewModels\ApprovalCard;
use Padosoft\LaravelFlowAdmin\ViewModels\KpiTile;
use Padosoft\LaravelFlowAdmin\ViewModels\OutboxRow;
use Padosoft\LaravelFlowAdmin\ViewModels\RunRow;
use Padosoft\LaravelFlowAdmin\ViewModels\ThroughputBar;
use Throwable;

class OverviewController extends Controller
{
    public function __construct(private readonly ReadModel $readModel) {}

    public function index(): View
    {
        $kpis = $this->safe(
            fn (): array => KpiTile::fromKpis($this->readModel->kpis()),
            [],
        );
        $throughput = $this->safe(
            fn (): array => ThroughputBar::fromSeries($this->readModel->throughputBuckets()),
            [],
        );

        $recentRuns = $this->safe(function (): array {
            $recentRunsPage = $this->readModel->listRuns(page: 1, perPage: 8);

            return array_map(RunRow::fromDto(...), $recentRunsPage->items);
        }, []);
        $recentFailed = $this->safe(function (): array {
            $recentFailedPage = $this->readModel->listRuns(status: 'failed', page: 1, perPage: 5);

            return array_map(RunRow::fromDto(...), $recentFailedPage->items);
        }, []);

        $pendingApprovals = $this->safe(
            fn (): array => array_map(
                ApprovalCard::fromDto(...),
                $this->readModel->pendingApprovals(limit: 5),
            ),
            [],
        );

        $pendingOutbox = $this->safe(
            fn (): array => array_map(
                OutboxRow::fromDto(...),
                $this->readModel->pendingWebhookOutbox(),
            ),
            [],
        );

        return view('flow-admin::pages.overview', [
            'kpis' => $kpis,
            'throughput' => $throughput,
            'recentRuns' => $recentRuns,
            'recentFailed' => $recentFailed,
            'pendingApprovals' => $pendingApprovals,
            'pendingOutbox' => $pendingOutbox,
            'route' => 'home',
            'pageTitle' => 'Overview',
            'breadcrumbs' => [
                ['label' => 'Overview'],
            ],
            'counts' => [
                'running' => $this->safe(
                    fn (): int => $this->readModel->listRuns(status: 'running', perPage: 1)->total,
                    0,
                ),
                'approvals' => $this->safe(
                    fn (): int => $this->readModel->listApprovals(status: 'pending', perPage: 1)->total,
                    0,
                ),
                'outbox' => $this->safe(
                    fn (): int => $this->readModel->listWebhookOutbox(status: 'pending', perPage: 1)->total,
                    0,
                ),
            ],
        ]);
    }

    /**
     * @template T
     *
     * @param  callable(): T  $callable
     * @param  T  $default
     * @return T
     */
    private function safe(callable $callable, mixed $default): mixed
    {
        try {
            return $callable();
        } catch (Throwable) {
            return $default;
        }
    }
}
