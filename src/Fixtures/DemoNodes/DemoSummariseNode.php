<?php

declare(strict_types=1);

namespace Padosoft\LaravelFlowAdmin\Fixtures\DemoNodes;

use Padosoft\LaravelFlow\Node\Attributes\FlowNode;
use Padosoft\LaravelFlow\Node\Attributes\Input;
use Padosoft\LaravelFlow\Node\Attributes\Output;
use Padosoft\LaravelFlow\Node\FlowNodeHandler;
use Padosoft\LaravelFlow\Node\NodeContext;
use Padosoft\LaravelFlow\Node\NodeResult;
use Padosoft\LaravelFlow\Node\PortProvenance;
use Padosoft\LaravelFlow\Node\PortType;

/**
 * Demo taint SOURCE — stands in for an LLM node, a page fetcher, a mail
 * ingester: anything whose output is words somebody outside chose.
 *
 * Exists so the Studio's taint rendering has something real to render in
 * demo mode; without a source in the demo catalog the amber wire and the
 * path message would be untestable and undemonstrable.
 */
#[FlowNode(type: 'demo.summarise', category: 'ai', name: 'Summarise (AI)', icon: 'sparkles', description: 'Summarises text with a model. Its output is untrusted.')]
final class DemoSummariseNode implements FlowNodeHandler
{
    #[Input(type: PortType::Text, required: true, label: 'Source text', key: 'text')]
    public string $text = '';

    #[Output(type: PortType::Text, label: 'Summary', key: 'summary', provenance: PortProvenance::Untrusted)]
    public string $summary = '';

    public function execute(NodeContext $context): NodeResult
    {
        return NodeResult::success(['summary' => 'a summary']);
    }
}
