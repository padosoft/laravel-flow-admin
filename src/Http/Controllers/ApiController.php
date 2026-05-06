<?php

declare(strict_types=1);

namespace Padosoft\LaravelFlowAdmin\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Padosoft\LaravelFlowAdmin\Contracts\ReadModel;

final class ApiController extends Controller
{
    public function __construct(private readonly ReadModel $readModel) {}

    public function search(Request $request): JsonResponse
    {
        $query = trim((string) $request->query('q', ''));

        $runs = $this->readModel->listRuns(query: $query === '' ? null : $query, perPage: 8)->items;
        $items = [];
        foreach ($runs as $run) {
            $items[] = [
                'type' => 'run',
                'id' => $run->id,
                'label' => $run->flowName,
                'meta' => $run->status,
                'url' => route('flow-admin.runs.show', ['id' => $run->id]),
            ];
        }

        $items[] = [
            'type' => 'nav',
            'id' => 'runs',
            'label' => 'Runs',
            'meta' => 'navigate',
            'url' => route('flow-admin.runs.index'),
        ];
        $items[] = [
            'type' => 'nav',
            'id' => 'approvals',
            'label' => 'Approvals',
            'meta' => 'navigate',
            'url' => route('flow-admin.approvals.index'),
        ];

        return response()->json(['items' => $items]);
    }

    public function live(): JsonResponse
    {
        $kpis = $this->readModel->kpis();

        return response()->json([
            'totalRuns' => $kpis->totalRuns,
            'failedRuns' => $kpis->failedRuns,
            'ts' => now()->toIso8601String(),
        ]);
    }
}
