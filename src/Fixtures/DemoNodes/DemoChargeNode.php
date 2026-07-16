<?php

declare(strict_types=1);

namespace Padosoft\LaravelFlowAdmin\Fixtures\DemoNodes;

use Padosoft\LaravelFlow\Node\Attributes\FlowNode;
use Padosoft\LaravelFlow\Node\Attributes\Input;
use Padosoft\LaravelFlow\Node\Attributes\Output;
use Padosoft\LaravelFlow\Node\FlowNodeHandler;
use Padosoft\LaravelFlow\Node\NodeContext;
use Padosoft\LaravelFlow\Node\NodeResult;
use Padosoft\LaravelFlow\Node\PortType;

/**
 * Real counterpart of `ArrayReadModel`'s `demo.charge` fixture catalog
 * entry. See `DemoTriggerNode` for why this class exists.
 */
#[FlowNode(type: 'demo.charge', category: 'payment', name: 'Charge Payment', icon: 'send', description: 'Charges the customer.')]
final class DemoChargeNode implements FlowNodeHandler
{
    #[Input(type: PortType::Bool, required: true, label: 'Authorized', key: 'authorized')]
    public bool $authorized;

    #[Output(type: PortType::Text, label: 'Receipt id', key: 'receipt')]
    public string $receipt;

    public function execute(NodeContext $context): NodeResult
    {
        return NodeResult::success(['receipt' => 'demo-receipt']);
    }
}
