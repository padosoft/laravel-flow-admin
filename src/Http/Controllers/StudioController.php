<?php

declare(strict_types=1);

namespace Padosoft\LaravelFlowAdmin\Http\Controllers;

use DateTimeInterface;
use Illuminate\Database\QueryException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Log;
use Illuminate\View\View;
use Padosoft\LaravelFlow\Contracts\DefinitionRepository;
use Padosoft\LaravelFlow\Graph\Exceptions\DefinitionLifecycleException;
use Padosoft\LaravelFlow\Graph\Exceptions\DefinitionNotFoundException;
use Padosoft\LaravelFlow\Graph\Exceptions\InvalidGraphException;
use Padosoft\LaravelFlow\Graph\GraphSerializer;
use Padosoft\LaravelFlow\Graph\GraphValidator;
use Padosoft\LaravelFlow\Graph\StoredDefinition;
use Padosoft\LaravelFlowAdmin\Contracts\ReadModel;
use Padosoft\LaravelFlowAdmin\Support\Authorize;
use Padosoft\LaravelFlowAdmin\Support\GraphRedactor;
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
     * The versioning page: a React island listing every stored version of
     * a flow (draft/published/archived), publishing a draft (with an
     * immutability confirmation), and rendering a node-level visual diff
     * between any two versions.
     */
    public function versions(string $name): View
    {
        return view('flow-admin::pages.studio-versions', [
            'route' => 'studio',
            'pageTitle' => "Versions of {$name}",
            'breadcrumbs' => [
                ['label' => 'Studio', 'url' => route('flow-admin.studio')],
                ['label' => $name, 'url' => route('flow-admin.studio.show', ['name' => $name])],
                ['label' => 'Versions'],
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

    /**
     * JSON: every stored version of $name (newest first) as lightweight
     * metadata — version number, lifecycle status, publish timestamp, and
     * checksum. Deliberately carries NO graph payload (and therefore no
     * node `config`), so it needs no `edit_definition` gate, matching the
     * read-only metadata surface of `graph()`/`show()`. An unknown flow
     * name yields an empty list, not a 404 — the page renders its own
     * "no versions yet" empty state.
     */
    public function versionList(string $name): JsonResponse
    {
        $versions = array_map(
            static fn (StoredDefinition $v): array => [
                'version' => $v->version,
                'status' => $v->status,
                'published_at' => $v->publishedAt?->format(DateTimeInterface::ATOM),
                'checksum' => $v->checksum,
            ],
            $this->definitions->versions($name),
        );

        usort($versions, static fn (array $a, array $b): int => $b['version'] <=> $a['version']);

        return response()->json(['name' => $name, 'versions' => $versions]);
    }

    /**
     * JSON: a node-level visual diff between two versions (`?from=&to=`).
     * The classification is computed SERVER-side and the response is a
     * REDACTED union graph — every node's `config` is stripped before it
     * leaves the server (via {@see GraphRedactor}), so the diff view never
     * exposes secrets even though it needs no edit gate. Every node and
     * connection carries a `diff_state` of added|removed|changed|unchanged
     * for the canvas overlay to color. `changed` is detected from a
     * content hash over type + config, so a pure layout move does not count.
     */
    public function diff(Request $request, string $name): JsonResponse
    {
        $from = (int) $request->query('from');
        $to = (int) $request->query('to');

        if ($from < 1 || $to < 1) {
            return response()->json([
                'message' => 'Both a "from" and a "to" version number (>= 1) are required.',
            ], 422);
        }

        try {
            $fromGraph = $this->definitions->find($name, $from)->graph;
            $toGraph = $this->definitions->find($name, $to)->graph;
        } catch (DefinitionNotFoundException) {
            return response()->json([
                'message' => "One of versions {$from} or {$to} of [{$name}] does not exist.",
            ], 404);
        }

        return response()->json($this->buildDiff($fromGraph, $toGraph, $from, $to));
    }

    /**
     * Publishes a specific DRAFT version, making it the runnable published
     * version (core archives any previously published one atomically).
     * Gated by `ActionAuthorizer::canEditDefinition()` — same authoring
     * boundary as editing/saving a draft. Core re-validates the graph on
     * publish, so a structurally-savable but semantically-incomplete draft
     * surfaces as a 422 here rather than a 500.
     */
    public function publish(Request $request, string $name): JsonResponse
    {
        return Authorize::action(
            'edit_definition',
            function () use ($request, $name): JsonResponse {
                $version = (int) $request->input('version');

                if ($version < 1) {
                    return response()->json([
                        'success' => false,
                        'message' => 'A version number (>= 1) to publish is required.',
                    ], 422);
                }

                try {
                    $published = $this->definitions->publish($name, $version);
                } catch (DefinitionNotFoundException) {
                    return response()->json([
                        'success' => false,
                        'message' => "Version {$version} of [{$name}] does not exist.",
                    ], 404);
                } catch (DefinitionLifecycleException) {
                    return response()->json([
                        'success' => false,
                        'message' => "Version {$version} of [{$name}] is not a draft and cannot be published.",
                    ], 409);
                } catch (InvalidGraphException $e) {
                    return response()->json([
                        'success' => false,
                        'message' => 'The version is invalid and was not published.',
                        'data' => ['violations' => $e->violations()],
                    ], 422);
                }

                return response()->json([
                    'success' => true,
                    'message' => "Version {$published->version} published.",
                    'data' => ['version' => $published->version, 'status' => $published->status],
                ]);
            },
            context: ['flowName' => $name],
        );
    }

    /**
     * @param  array<string, mixed>  $fromGraph  a GraphSerializer envelope
     * @param  array<string, mixed>  $toGraph  a GraphSerializer envelope
     * @return array<string, mixed>
     */
    private function buildDiff(array $fromGraph, array $toGraph, int $from, int $to): array
    {
        $fromNodes = $this->indexNodesById($fromGraph);
        $toNodes = $this->indexNodesById($toGraph);

        $nodes = [];

        foreach ($toNodes as $id => $node) {
            if (! isset($fromNodes[$id])) {
                $state = 'added';
            } elseif ($this->nodeContentHash($node) !== $this->nodeContentHash($fromNodes[$id])) {
                $state = 'changed';
            } else {
                $state = 'unchanged';
            }

            $nodes[] = $this->diffNode($node, $state);
        }

        foreach ($fromNodes as $id => $node) {
            if (! isset($toNodes[$id])) {
                $nodes[] = $this->diffNode($node, 'removed');
            }
        }

        $fromWires = $this->indexConnectionsByKey($fromGraph);
        $toWires = $this->indexConnectionsByKey($toGraph);
        $connections = [];

        foreach ($toWires as $key => $wire) {
            $connections[] = $wire + ['diff_state' => isset($fromWires[$key]) ? 'unchanged' : 'added'];
        }

        foreach ($fromWires as $key => $wire) {
            if (! isset($toWires[$key])) {
                $connections[] = $wire + ['diff_state' => 'removed'];
            }
        }

        return [
            'from' => $from,
            'to' => $to,
            'summary' => [
                'added' => count(array_filter($nodes, static fn (array $n): bool => $n['diff_state'] === 'added')),
                'removed' => count(array_filter($nodes, static fn (array $n): bool => $n['diff_state'] === 'removed')),
                'changed' => count(array_filter($nodes, static fn (array $n): bool => $n['diff_state'] === 'changed')),
            ],
            'graph' => [
                'schema_version' => $toGraph['schema_version'] ?? 1,
                'nodes' => $nodes,
                'connections' => $connections,
            ],
        ];
    }

    /**
     * @param  array<string, mixed>  $graph
     * @return array<string, array<string, mixed>>
     */
    private function indexNodesById(array $graph): array
    {
        $indexed = [];

        foreach (is_array($graph['nodes'] ?? null) ? $graph['nodes'] : [] as $node) {
            if (is_array($node) && is_string($node['id'] ?? null)) {
                $indexed[$node['id']] = $node;
            }
        }

        return $indexed;
    }

    /**
     * @param  array<string, mixed>  $graph
     * @return array<string, array<string, mixed>>
     */
    private function indexConnectionsByKey(array $graph): array
    {
        $indexed = [];

        foreach (is_array($graph['connections'] ?? null) ? $graph['connections'] : [] as $wire) {
            if (! is_array($wire)) {
                continue;
            }

            $key = ($wire['sourceNodeId'] ?? '') . '.' . ($wire['sourcePortKey'] ?? '')
                . '>' . ($wire['targetNodeId'] ?? '') . '.' . ($wire['targetPortKey'] ?? '');

            $indexed[$key] = [
                'sourceNodeId' => $wire['sourceNodeId'] ?? '',
                'sourcePortKey' => $wire['sourcePortKey'] ?? '',
                'targetNodeId' => $wire['targetNodeId'] ?? '',
                'targetPortKey' => $wire['targetPortKey'] ?? '',
            ];
        }

        return $indexed;
    }

    /**
     * A content hash over a node's meaningful fields (type + config) so a
     * pure layout move (position only) is never reported as "changed".
     *
     * @param  array<string, mixed>  $node
     */
    private function nodeContentHash(array $node): string
    {
        return md5((string) json_encode([
            'type' => $node['type'] ?? null,
            'config' => $node['config'] ?? [],
        ]));
    }

    /**
     * Redacts a node for the diff overlay: keeps only id/type/position
     * (never `config`, matching {@see GraphRedactor}) and tags its diff
     * state for the canvas to color.
     *
     * @param  array<string, mixed>  $node
     * @return array<string, mixed>
     */
    private function diffNode(array $node, string $state): array
    {
        return [
            'id' => $node['id'] ?? '',
            'type' => $node['type'] ?? '',
            'position' => $node['position'] ?? null,
            'diff_state' => $state,
        ];
    }
}
