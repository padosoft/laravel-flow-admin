<?php

declare(strict_types=1);

namespace Padosoft\LaravelFlowAdmin\Http\Controllers;

use Illuminate\Database\QueryException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Log;
use Illuminate\View\View;
use Padosoft\LaravelFlow\Contracts\DefinitionRepository;
use Padosoft\LaravelFlow\Graph\Exceptions\InvalidGraphException;
use Padosoft\LaravelFlow\Graph\GraphSerializer;
use Padosoft\LaravelFlow\Graph\GraphValidator;
use Padosoft\LaravelFlowAdmin\Contracts\ReadModel;
use Padosoft\LaravelFlowAdmin\Support\Authorize;
use Padosoft\LaravelFlowAdmin\ViewModels\DefinitionRow;
use Throwable;

final class StudioController extends Controller
{
    public function __construct(
        private readonly ReadModel $readModel,
        private readonly DefinitionRepository $definitions,
        private readonly GraphSerializer $serializer,
        private readonly GraphValidator $validator,
    ) {}

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

    /**
     * The full node-type catalog for the editor's palette — not scoped to
     * any single graph, unlike graph()'s `catalog` subset. No secrets in
     * this payload (node-type metadata only), so unlike editGraph()/
     * storeDraft() it does not need `ActionAuthorizer::canEditDefinition()` —
     * same visibility level as graph()'s own catalog subset.
     */
    public function catalog(): JsonResponse
    {
        return response()->json(['catalog' => $this->readModel->catalog()]);
    }

    public function edit(string $name): View
    {
        return view('flow-admin::pages.studio-edit', [
            'route' => 'studio',
            'pageTitle' => "Edit {$name}",
            'breadcrumbs' => [
                ['label' => 'Studio', 'url' => route('flow-admin.studio')],
                ['label' => $name, 'url' => route('flow-admin.studio.show', ['name' => $name])],
                ['label' => 'Edit'],
            ],
            'counts' => $this->counts(),
            'flowName' => $name,
        ]);
    }

    /**
     * JSON API backing the editor canvas: `{graph, catalog, version,
     * status}` with node `config` INCLUDED (unlike graph()), so the
     * inspector panel has real values to populate. Gated by
     * `ActionAuthorizer::canEditDefinition()` — this is the one place in
     * Studio that can leak a node's secrets to the browser, deliberately.
     */
    public function editGraph(string $name): JsonResponse
    {
        return Authorize::action(
            'edit_definition',
            function () use ($name): JsonResponse {
                $result = $this->readModel->editableGraph($name);

                if ($result === null) {
                    return response()->json([
                        'message' => "No version of [{$name}] found.",
                    ], 404);
                }

                return response()->json($result);
            },
            context: ['flowName' => $name],
        );
    }

    /**
     * Saves the edited graph as a NEW draft version. The client's inline
     * type-validation (invalid wire renders red, Save disabled) is
     * advisory only — this endpoint re-validates structurally
     * (`GraphSerializer::fromArray()`) and semantically
     * (`GraphValidator::validate()`) before persisting, exactly like
     * `GraphTransfer::importDraft()` does for imported graphs. Gated by
     * `ActionAuthorizer::canEditDefinition()`.
     */
    public function storeDraft(Request $request, string $name): JsonResponse
    {
        return Authorize::action(
            'edit_definition',
            function () use ($request, $name): JsonResponse {
                /** @var array<string, mixed> $payload */
                $payload = (array) $request->json()->all();

                try {
                    $graph = $this->serializer->fromArray($payload);
                    $this->validator->validate($graph);
                } catch (InvalidGraphException $e) {
                    return response()->json([
                        'success' => false,
                        'message' => 'The graph is invalid and was not saved.',
                        'data' => ['violations' => $e->violations()],
                    ], 422);
                }

                try {
                    $stored = $this->definitions->createDraft($name, $graph);
                } catch (Throwable $e) {
                    // Exception class + a redaction-safe detail only — never
                    // the full context, which could carry the graph's
                    // contents (same spirit as EloquentReadModel::graph()).
                    // A QueryException's message interpolates the bound
                    // values into the SQL, and this INSERT carries the
                    // UNREDACTED graph (node `config` may hold secrets), so
                    // for that type log only the SQLSTATE/driver code, never
                    // the raw message. Other exception types don't embed the
                    // persisted payload, so their message is safe to keep.
                    Log::warning('laravel-flow-admin: failed to save a flow definition draft', [
                        'name' => $name,
                        'exception' => $e::class,
                        'detail' => $e instanceof QueryException ? 'SQLSTATE ' . $e->getCode() : $e->getMessage(),
                    ]);

                    return response()->json([
                        'success' => false,
                        'message' => 'Could not save the draft. Try again.',
                        'data' => [],
                    ], 500);
                }

                return response()->json([
                    'success' => true,
                    'message' => "Draft version {$stored->version} saved.",
                    'data' => ['version' => $stored->version, 'status' => $stored->status],
                ], 201);
            },
            context: ['flowName' => $name],
        );
    }
}
