<?php

declare(strict_types=1);

namespace Padosoft\LaravelFlowAdmin\Tests\Stubs;

use Padosoft\LaravelFlow\Node\Attributes\FlowNode;
use Padosoft\LaravelFlow\Node\Attributes\Output;
use Padosoft\LaravelFlow\Node\FlowNodeHandler;
use Padosoft\LaravelFlow\Node\NodeContext;
use Padosoft\LaravelFlow\Node\NodeResult;
use Padosoft\LaravelFlow\Node\PortType;

/**
 * Minimal single-output fixture node, registered ad hoc in tests that need
 * a real, `GraphValidator`-publishable node type — `EloquentReadModelTest`'s
 * `graph()` coverage needs a genuinely PUBLISHED `StoredDefinition`, which
 * only core's semantic validator (not `createDraft()`) enforces.
 */
#[FlowNode(type: 'test.studio.demo-trigger', category: 'testing')]
final class DemoTriggerNode implements FlowNodeHandler
{
    #[Output(type: PortType::Json)]
    public array $out;

    public function execute(NodeContext $context): NodeResult
    {
        return NodeResult::success(['out' => []]);
    }
}
