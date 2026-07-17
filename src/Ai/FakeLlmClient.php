<?php

declare(strict_types=1);

namespace Padosoft\LaravelFlowAdmin\Ai;

use Padosoft\LaravelFlowAI\Contracts\LlmClient;
use Padosoft\LaravelFlowAI\Llm\LlmRequest;
use Padosoft\LaravelFlowAI\Llm\LlmResponse;

/**
 * A deterministic, network-free {@see LlmClient} for local dev and E2E only.
 *
 * The real AI flow builder calls a live LLM; that's impractical for
 * Playwright and CI, so binding this fake (via `FLOW_ADMIN_FAKE_LLM=1`, the
 * same dev/E2E opt-in posture as `FLOW_ADMIN_AUTHORIZER=allow`) lets the
 * AI-builder happy path be exercised end-to-end. It returns a fixed, VALID
 * `{nodes, connections}` graph built from a node type the demo (`array`)
 * adapter registers, so `FlowBuilderService::build()` maps + validates it
 * into a real draft. NEVER bound in production.
 */
final class FakeLlmClient implements LlmClient
{
    public function __construct(private readonly string $nodeType = 'demo.trigger') {}

    public function complete(LlmRequest $request): LlmResponse
    {
        $graph = json_encode([
            'nodes' => [
                ['id' => 'ai_generated', 'type' => $this->nodeType, 'config' => []],
            ],
            'connections' => [],
        ], JSON_THROW_ON_ERROR);

        return new LlmResponse(
            content: $graph,
            model: $request->model,
            promptTokens: 12,
            completionTokens: 24,
            stopReason: 'stop',
        );
    }
}
