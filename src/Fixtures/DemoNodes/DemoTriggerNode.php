<?php

declare(strict_types=1);

namespace Padosoft\LaravelFlowAdmin\Fixtures\DemoNodes;

use Padosoft\LaravelFlow\Node\Attributes\FlowNode;
use Padosoft\LaravelFlow\Node\Attributes\Output;
use Padosoft\LaravelFlow\Node\FlowNodeHandler;
use Padosoft\LaravelFlow\Node\NodeContext;
use Padosoft\LaravelFlow\Node\NodeResult;
use Padosoft\LaravelFlow\Node\PortType;

/**
 * Real, registerable counterpart of `ArrayReadModel`'s `demo.trigger`
 * fixture catalog entry — same type/name/category/icon/description/ports.
 * Registered into the real `NodeRegistry` only in `array` (demo) adapter
 * mode (see `FlowAdminServiceProvider::registerDemoNodesIfInDemoMode()`)
 * so `GraphValidator` can actually validate a Studio-composed graph built
 * from the demo fixture's own palette — the fixture's `config`/`catalog`
 * response and the real node catalog must agree on what "demo.trigger"
 * IS, or every save in demo mode fails with "Unknown node type".
 */
#[FlowNode(type: 'demo.trigger', category: 'trigger', name: 'Order Received', icon: 'play', description: 'Starts the checkout flow.')]
final class DemoTriggerNode implements FlowNodeHandler
{
    /** @var array<string, mixed> */
    #[Output(type: PortType::Json, label: 'Order payload', key: 'out')]
    public array $out;

    public function execute(NodeContext $context): NodeResult
    {
        return NodeResult::success(['out' => []]);
    }
}
