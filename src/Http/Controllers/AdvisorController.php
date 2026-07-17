<?php

declare(strict_types=1);

namespace Padosoft\LaravelFlowAdmin\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Log;
use Illuminate\View\View;
use Padosoft\LaravelFlowAdmin\Contracts\ReadModel;
use Padosoft\LaravelFlowAdmin\Support\Authorize;
use Padosoft\LaravelFlowAI\Advisor\FlowAdvisor;
use Padosoft\LaravelFlowAI\Advisor\Suggestion;
use Throwable;

/**
 * The Flow Advisor inbox: runs padosoft/laravel-flow-ai's deterministic
 * {@see FlowAdvisor} (no LLM — every finding is derived from persisted run
 * history) across a flow's history and surfaces each {@see Suggestion} as a
 * card the operator can act on.
 *
 * IMPORTANT: scanning is a MUTATION, not a read. `FlowAdvisor::suggest()`
 * persists a NEW draft `GraphDefinition` version for every flow it finds a
 * suggestion for (the draft carries the finding rationale in
 * `metadata['advisor']`); the operator then reviews/publishes that draft
 * through the existing versioning UI. So the scan lives behind a gated POST
 * (`edit_definition`, the same authoring boundary as saving a draft), not a
 * GET — and it is rate-limited because a scan reads run history and can write
 * several draft rows.
 */
final class AdvisorController extends Controller
{
    public function __construct(private readonly ReadModel $readModel) {}

    public function index(): View
    {
        return view('flow-admin::pages.advisor', [
            'route' => 'advisor',
            'pageTitle' => 'Advisor',
            'breadcrumbs' => [['label' => 'Advisor']],
            'counts' => [
                'running' => $this->readModel->listRuns(status: 'running', perPage: 1)->total,
                'approvals' => $this->readModel->listApprovals(status: 'pending', perPage: 1)->total,
                'outbox' => $this->readModel->listWebhookOutbox(status: 'pending', perPage: 1)->total,
            ],
            // Only offer the scan affordance when the optional AI package is
            // present (same posture as the Studio "Build with AI" panel).
            'advisorAvailable' => class_exists(FlowAdvisor::class),
        ]);
    }

    /**
     * Runs the advisor across all candidate flows and returns the resulting
     * suggestions. Each suggestion references the DRAFT version the advisor
     * just created for it (never published — the operator decides). Gated by
     * `ActionAuthorizer::canEditDefinition()` because the scan writes draft
     * definition versions.
     */
    public function scan(): JsonResponse
    {
        return Authorize::action(
            'edit_definition',
            function (): JsonResponse {
                // Inside the auth gate (uniform 403 regardless of package
                // presence — the route is registered unconditionally, same
                // no-oracle posture as the ai-build endpoint).
                if (! class_exists(FlowAdvisor::class)) {
                    return response()->json([
                        'success' => false,
                        'message' => 'The Flow Advisor is not available (padosoft/laravel-flow-ai is not installed).',
                    ], 404);
                }

                try {
                    /** @var FlowAdvisor $advisor */
                    $advisor = app(FlowAdvisor::class);
                    $suggestions = $advisor->suggest();
                } catch (Throwable $e) {
                    // Never leak the raw message — the advisor touches run
                    // history and repository internals. Sanitized 500, class
                    // only.
                    Log::warning('laravel-flow-admin: Flow Advisor scan failed', [
                        'exception' => $e::class,
                    ]);

                    return response()->json([
                        'success' => false,
                        'message' => 'The advisor scan could not complete. Try again.',
                    ], 500);
                }

                return response()->json([
                    'success' => true,
                    'message' => $suggestions === []
                        ? 'No suggestions — the advisor found nothing notable in recent run history.'
                        : count($suggestions) . ' suggestion(s) found.',
                    'suggestions' => array_map($this->presentSuggestion(...), $suggestions),
                ]);
            },
        );
    }

    /**
     * Shapes a {@see Suggestion} for the inbox. The finding rationale is
     * already redactor-passed by the advisor, but bound its size defensively
     * (same spirit as StudioController::boundViolations) so an unexpectedly
     * large analyzer payload can't bloat the response.
     *
     * @return array<string, mixed>
     */
    private function presentSuggestion(Suggestion $suggestion): array
    {
        return [
            'flow' => $suggestion->definitionName,
            'draft_version' => $suggestion->draftVersion,
            'draft_id' => $suggestion->draftVersionId(),
            'finding' => [
                'type' => $suggestion->finding->type,
                'summary' => mb_strlen($suggestion->finding->summary) > 300
                    ? mb_substr($suggestion->finding->summary, 0, 300) . '…'
                    : $suggestion->finding->summary,
                'rationale' => $this->boundRationale($suggestion->finding->rationale),
            ],
        ];
    }

    /**
     * Caps the machine-readable rationale to a bounded JSON size so a runaway
     * analyzer payload never dominates the response. Keeps at most 20 top
     * level keys and truncates any string leaf to 300 chars.
     *
     * @param  array<string, mixed>  $rationale
     * @return array<string, mixed>
     */
    private function boundRationale(array $rationale): array
    {
        $bounded = [];

        foreach (array_slice($rationale, 0, 20, true) as $key => $value) {
            $bounded[$key] = $this->boundValue($value);
        }

        return $bounded;
    }

    private function boundValue(mixed $value): mixed
    {
        if (is_string($value)) {
            return mb_strlen($value) > 300 ? mb_substr($value, 0, 300) . '…' : $value;
        }

        if (is_array($value)) {
            return array_map($this->boundValue(...), array_slice($value, 0, 20, true));
        }

        return $value;
    }
}
