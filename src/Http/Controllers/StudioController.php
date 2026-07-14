<?php

declare(strict_types=1);

namespace Padosoft\LaravelFlowAdmin\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Routing\Controller;
use Illuminate\View\View;
use Padosoft\LaravelFlowAdmin\Contracts\ReadModel;
use Padosoft\LaravelFlowAdmin\ViewModels\DefinitionRow;

final class StudioController extends Controller
{
    public function __construct(private readonly ReadModel $readModel) {}

    private function counts(): array
    {
        return [
            'running' => $this->readModel->listRuns(status: 'running', perPage: 1)->total,
            'approvals' => $this->readModel->listApprovals(status: 'pending', perPage: 1)->total,
            'outbox' => $this->readModel->listWebhookOutbox(status: 'pending', perPage: 1)->total,
        ];
    }

    public function index(): View
    {
        return view('flow-admin::pages.studio', [
            'route' => 'studio',
            'pageTitle' => 'Studio',
            'breadcrumbs' => [['label' => 'Studio']],
            'counts' => $this->counts(),
            'definitions' => array_map(DefinitionRow::fromDto(...), $this->readModel->definitions()),
        ]);
    }

    public function show(string $name): View
    {
        return view('flow-admin::pages.studio-show', [
            'route' => 'studio',
            'pageTitle' => $name,
            'breadcrumbs' => [
                ['label' => 'Studio', 'url' => route('flow-admin.studio')],
                ['label' => $name],
            ],
            'counts' => $this->counts(),
            'flowName' => $name,
        ]);
    }

    /**
     * JSON API backing the React canvas: `{graph, catalog}` for the
     * requested flow's latest PUBLISHED version, or a 404 body when there
     * isn't one — the client-side canvas renders an explicit "not
     * published yet" empty state for that case rather than a blank canvas.
     */
    public function graph(string $name): JsonResponse
    {
        $result = $this->readModel->graph($name);

        if ($result === null) {
            return response()->json([
                'message' => "No published version of [{$name}] found.",
            ], 404);
        }

        return response()->json($result);
    }
}
