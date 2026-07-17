<?php

declare(strict_types=1);

namespace Padosoft\LaravelFlowAdmin\Http\Controllers;

use Closure;
use DateTimeInterface;
use Illuminate\Database\QueryException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Log;
use Illuminate\View\View;
use Padosoft\LaravelFlow\Contracts\DefinitionRepository;
use Padosoft\LaravelFlow\Executor\DryRun\DryRunPlanner;
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
use Padosoft\LaravelFlowAI\Builder\FlowBuilderService;
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
            // The "Build with AI" panel is only wired when the optional
            // padosoft/laravel-flow-ai package is installed — the view omits
            // the data-ai-build-url attribute otherwise, so the React island
            // simply doesn't render the AI button.
            'aiBuilderAvailable' => class_exists(FlowBuilderService::class),
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
        try {
            $stored = $this->definitions->versions($name);
        } catch (Throwable $e) {
            return $this->repositoryFailure('list versions of', $name, $e);
        }

        $versions = array_map(
            static fn (StoredDefinition $v): array => [
                'version' => $v->version,
                'status' => $v->status,
                'published_at' => $v->publishedAt?->format(DateTimeInterface::ATOM),
                'checksum' => $v->checksum,
            ],
            $stored,
        );

        usort($versions, static fn (array $a, array $b): int => $b['version'] <=> $a['version']);

        return response()->json(['name' => $name, 'versions' => $versions]);
    }

    /**
     * JSON: a node-level visual diff between two versions (`?from=&to=`).
     * The classification is computed SERVER-side and the response strips
     * every node's `config` before it leaves the server (same posture as
     * {@see GraphRedactor}, applied here node-by-node in {@see self::diffNode()}),
     * so the diff view never exposes secrets even though it needs no edit
     * gate. Every node and connection carries a `diff_state` of
     * added|removed|changed|unchanged for the canvas overlay to color.
     * `changed` is detected from a content hash over type + config, so a
     * pure layout move does not count.
     */
    public function diff(Request $request, string $name): JsonResponse
    {
        $validated = $request->validate([
            'from' => 'required|integer|min:1',
            'to' => 'required|integer|min:1',
        ]);
        $from = (int) $validated['from'];
        $to = (int) $validated['to'];

        try {
            $fromGraph = $this->definitions->find($name, $from)->graph;
            $toGraph = $this->definitions->find($name, $to)->graph;
        } catch (DefinitionNotFoundException) {
            return response()->json([
                'message' => "One of versions {$from} or {$to} of [{$name}] does not exist.",
            ], 404);
        } catch (Throwable $e) {
            return $this->repositoryFailure('diff', $name, $e);
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
                $validated = $request->validate(['version' => 'required|integer|min:1']);
                $version = (int) $validated['version'];

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
                } catch (Throwable $e) {
                    return $this->repositoryFailure('publish a version of', $name, $e, withSuccessFlag: true);
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
     * Uniform sanitized 500 for an unexpected repository failure (a DB
     * outage, or a `DefinitionSignatureException` when signing is enabled
     * and a stored row was tampered with). Never returns the raw exception
     * message to the client and, for a `QueryException`, never LOGS the
     * message either — it interpolates bound values (including graph config)
     * into the SQL. Same posture as `storeDraft()`.
     */
    private function repositoryFailure(string $action, string $name, Throwable $e, bool $withSuccessFlag = false): JsonResponse
    {
        Log::warning("laravel-flow-admin: failed to {$action} a flow definition", [
            'name' => $name,
            'exception' => $e::class,
            'detail' => $e instanceof QueryException ? 'SQLSTATE ' . $e->getCode() : $e->getMessage(),
        ]);

        $body = ['message' => 'Something went wrong. Try again.'];

        if ($withSuccessFlag) {
            $body = ['success' => false, 'message' => 'Could not publish. Try again.', 'data' => []];
        }

        return response()->json($body, 500);
    }

    /**
     * Statically plans the POSTed graph via core's `DryRunPlanner` and returns
     * the Kahn-wave execution plan + cost estimate, executing NO handler and
     * writing ZERO rows (by construction of the planner). Advisory only, so it
     * needs no edit gate: the response is structural (node ids, wave grouping,
     * cost dimensions), never node `config` — and the node types + `#[Cost]`
     * hints it exposes are already public via the ungated `catalog()` endpoint.
     * `DryRunPlanner` is method-injected from the container.
     */
    public function dryRun(Request $request, DryRunPlanner $planner, string $name): JsonResponse
    {
        /** @var array<string, mixed> $payload */
        $payload = (array) $request->json()->all();

        try {
            $graph = $this->serializer->fromArray($payload);
            // Semantic validation too (like storeDraft): GraphDefinition's
            // constructor already rejects structural problems (cycles,
            // connections to unknown nodes) inside fromArray(), but only
            // GraphValidator catches an UNREGISTERED node type, an
            // incompatible port-type wiring, or an unwired required input —
            // any of which the planner would otherwise plan into a
            // meaningless "trivially valid" result instead of a clear error.
            $this->validator->validate($graph);
        } catch (InvalidGraphException $e) {
            return response()->json([
                'success' => false,
                'message' => 'The graph is invalid and cannot be planned.',
                'data' => ['violations' => $e->violations()],
            ], 422);
        }

        try {
            $result = $planner->plan($graph);
        } catch (Throwable $e) {
            // An unexpected planner/container error must not fall through to
            // Laravel's default (often HTML, debug-leaking) renderer. Sanitized
            // JSON 500; log class + a redaction-safe detail only, never the raw
            // message (the graph payload can carry node config).
            return $this->repositoryFailure('dry-run', $name, $e);
        }

        return response()->json([
            'flow' => $name,
            'plan' => $result['plan']->toArray(),
            'cost' => $result['cost']->toArray(),
        ]);
    }

    /**
     * Turns a natural-language prompt into a VALIDATED draft graph via
     * padosoft/laravel-flow-ai's {@see FlowBuilderService}, and returns the
     * serialized envelope for the editor to load onto the canvas — it does
     * NOT persist anything. The operator reviews the proposal and then saves
     * it through the existing (separately-gated) `storeDraft()` flow, so the
     * AI never writes a definition on its own.
     *
     * Gated by `ActionAuthorizer::canEditDefinition()` — same authoring
     * boundary as editing/saving a draft, and it consumes a (billable) model
     * call. `FlowBuilderService::build()` already runs the result through
     * core's `GraphValidator`, so a model that hallucinates an invalid graph
     * surfaces here as a typed 422 with the concrete violations, never a
     * broken draft. The service is resolved from the container (not
     * method-injected) behind a class_exists() guard so the endpoint degrades
     * to a clean 404 if the optional AI package is absent, rather than a
     * container resolution error.
     */
    public function aiBuild(Request $request, string $name): JsonResponse
    {
        return Authorize::action(
            'edit_definition',
            function () use ($request): JsonResponse {
                // Checked INSIDE the authorization gate (not before it) so an
                // unauthorized caller always gets a uniform 403 regardless of
                // whether the optional AI package is installed — otherwise the
                // 404-vs-403 split would leak package-presence to anyone, a
                // feature-detection oracle on an otherwise deny-by-default
                // surface.
                if (! class_exists(FlowBuilderService::class)) {
                    return response()->json([
                        'success' => false,
                        'message' => 'The AI flow builder is not available (padosoft/laravel-flow-ai is not installed).',
                    ], 404);
                }

                $validated = $request->validate([
                    'prompt' => [
                        'required', 'string', 'max:4000',
                        // Trimmed minimum: a plain `min:3` counts RAW length, so
                        // a whitespace-only prompt ("   ") would pass and still
                        // spend a billable model call. Enforce ≥3 non-whitespace
                        // characters server-side, independent of the UI trim.
                        static function (string $attribute, mixed $value, Closure $fail): void {
                            if (! is_string($value) || mb_strlen(trim($value)) < 3) {
                                $fail('The prompt must contain at least 3 non-whitespace characters.');
                            }
                        },
                    ],
                ]);

                // Send the trimmed prompt to the builder — leading/trailing
                // whitespace carries no intent and would only pad the model call.
                $prompt = trim((string) $validated['prompt']);
                // The model is an OPERATOR cost/policy decision, taken from
                // config only — deliberately NOT client-supplied, so an actor
                // with edit rights cannot force an arbitrary/most-expensive
                // model past the configured default and run up spend.
                $model = (string) config('flow-admin.ai.model', 'claude-sonnet-5');

                try {
                    // Resolve INSIDE the try: the AI pack builds FlowBuilderService
                    // through a guarded LLM client whose construction can itself
                    // throw (e.g. a malformed provider base_url in config), and
                    // that would otherwise escape before build() and bypass this
                    // sanitized 500 into Laravel's default exception response.
                    /** @var FlowBuilderService $builder */
                    $builder = app(FlowBuilderService::class);
                    $result = $builder->build($prompt, $model);
                } catch (Throwable $e) {
                    // A real LLM client can throw on transport/API errors
                    // (build() only catches PolicyDeniedException internally).
                    // Never leak the raw message — it can echo the prompt or
                    // provider internals. Sanitized 500, class-only log.
                    Log::warning('laravel-flow-admin: AI flow builder failed', [
                        'exception' => $e::class,
                    ]);

                    return response()->json([
                        'success' => false,
                        'message' => 'The AI builder could not complete. Try again.',
                    ], 500);
                }

                // A real null-check (not just !success) narrows $result->graph
                // from ?GraphDefinition to GraphDefinition for the serializer —
                // success() always carries a graph and failed() never does, but
                // checking the property directly keeps that invariant explicit.
                if (! $result->success || $result->graph === null) {
                    return response()->json([
                        'success' => false,
                        'message' => 'The AI could not produce a valid graph from that prompt.',
                        // These are usually GraphValidator strings (short, safe —
                        // same as /draft surfaces), but the AI pack also folds a
                        // guardrail PolicyDeniedException message into this list,
                        // which originates outside this package. Bound count +
                        // length defensively so a future guardrail change can't
                        // dump large/leaky text straight to the browser.
                        'data' => ['violations' => $this->boundViolations($result->errors)],
                    ], 422);
                }

                return response()->json([
                    'success' => true,
                    'message' => 'Draft graph generated. Review it, then save it as a draft.',
                    'graph' => $this->serializer->toArray($result->graph),
                ]);
            },
            context: ['flowName' => $name],
        );
    }

    /**
     * Defensive bound on the validation reasons returned to the browser from
     * the AI builder: at most 20 entries, each truncated to 300 chars. The
     * GraphValidator strings these normally carry are already short; the cap
     * exists so a future guardrail-layer message (folded into the same list
     * by the AI pack, outside this package's control) can never dump large or
     * leaky text to the client unbounded.
     *
     * @param  list<string>  $violations
     * @return list<string>
     */
    private function boundViolations(array $violations): array
    {
        return array_map(
            static fn (string $violation): string => mb_strlen($violation) > 300
                ? mb_substr($violation, 0, 300) . '…'
                : $violation,
            array_slice(array_values($violations), 0, 20),
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
     * pure layout move (position only) is never reported as "changed". The
     * config is canonicalized (recursively key-sorted) first, so two
     * semantically-identical configs that differ only in key order are not
     * misclassified as "changed".
     *
     * @param  array<string, mixed>  $node
     */
    private function nodeContentHash(array $node): string
    {
        return md5((string) json_encode([
            'type' => $node['type'] ?? null,
            'config' => $this->canonicalize($node['config'] ?? []),
        ]));
    }

    /**
     * Recursively key-sorts arrays so a hash over the result is independent
     * of key insertion order (list arrays keep their element order).
     */
    private function canonicalize(mixed $value): mixed
    {
        if (! is_array($value)) {
            return $value;
        }

        $canonical = array_map($this->canonicalize(...), $value);

        if (! array_is_list($canonical)) {
            ksort($canonical);
        }

        return $canonical;
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
